<?php

declare(strict_types=1);

/**
 * نصاب وب رشن.
 *
 * چرا وجود دارد: مخاطب ما آرایشگاه است، نه شرکت نرم‌افزاری. روی هاست
 * اشتراکی ایرانی معمولاً SSH نیست، پس `php tools/migrate.php` قابل اجرا
 * نیست. این فایل همان کار را از مرورگر انجام می‌دهد.
 *
 * امنیت: به‌محض اینکه نصب موفق تمام شود، فایل قفل
 * `storage/installed.lock` نوشته می‌شود و این صفحه دیگر باز نمی‌شود.
 * همان الگویی که وردپرس استفاده می‌کند. برای نصب مجدد، فایل قفل را
 * دستی پاک کنید.
 *
 * این فایل عمداً به bootstrap برنامه وابسته نیست تا وقتی .env هنوز
 * ساخته نشده هم بالا بیاید.
 */

const RESHEN_ROOT = __DIR__ . '/..';
const LOCK_FILE = RESHEN_ROOT . '/storage/installed.lock';

session_start();

// فایل قفل همان لحظه‌ای نوشته می‌شود که مهاجرت‌ها تمام می‌شوند، یعنی
// پیش از اینکه کاربر صفحهٔ «تمام شد» را ببیند. بدون این استثنا، کاربر
// درست بعد از نصب موفق با پیام «قبلاً نصب شده» روبه‌رو می‌شود و
// دستورالعمل‌های بعدی (پیامک، کرون) را هرگز نمی‌بیند.
$justInstalled = ($_GET['step'] ?? '') === 'done' && ($_SESSION['install_done'] ?? false) === true;

if (is_file(LOCK_FILE) && !$justInstalled) {
    http_response_code(403);
    render_shell('نصب قبلاً انجام شده', [
        'بخش' => [[
            'label' => 'وضعیت',
            'status' => 'fail',
            'value' => 'قفل شده',
            'hint' => 'رشن قبلاً روی این هاست نصب شده است. اگر واقعاً می‌خواهید از نو نصب کنید، '
                . 'فایل storage/installed.lock را پاک کنید. توجه: نصب مجدد دادهٔ موجود را دست نمی‌زند، '
                . 'ولی این صفحه یک در باز است و نباید بی‌دلیل بازش کنید.',
        ]],
    ], null);
    exit;
}

$step = $_GET['step'] ?? 'check';
$errors = [];

// ─── گام ۲: نوشتن .env و اجرای مهاجرت ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_step'] ?? '') === 'config') {
    if (!hash_equals((string) ($_SESSION['install_csrf'] ?? ''), (string) ($_POST['_csrf'] ?? ''))) {
        $errors[] = 'نشست منقضی شده است. صفحه را تازه کنید و دوباره تلاش کنید.';
    } else {
        $db = [
            'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
            'port' => trim((string) ($_POST['db_port'] ?? '3306')),
            'name' => trim((string) ($_POST['db_name'] ?? '')),
            'user' => trim((string) ($_POST['db_user'] ?? '')),
            'pass' => (string) ($_POST['db_pass'] ?? ''),
        ];
        $siteUrl = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');

        if ($db['name'] === '' || $db['user'] === '') {
            $errors[] = 'نام دیتابیس و نام کاربری را پر کنید.';
        }

        if ($errors === []) {
            try {
                $pdo = new PDO(
                    "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4",
                    $db['user'],
                    $db['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (Throwable $e) {
                $errors[] = 'اتصال به دیتابیس ناموفق بود: ' . $e->getMessage()
                    . ' — در cPanel، نام دیتابیس و کاربر معمولاً پیشوند حساب را دارند، مثل «user_reshen».';
            }
        }

        if ($errors === []) {
            $written = write_env($db, $siteUrl);
            if ($written !== true) {
                $errors[] = $written;
            }
        }

        if ($errors === []) {
            // حالا که .env هست، برنامه را بالا بیاور و مهاجرت کن.
            require RESHEN_ROOT . '/app/bootstrap.php';

            $migrator = new App\Core\Migrator(RESHEN_ROOT . '/database/migrations');
            $report = $migrator->run();

            foreach ($report as $row) {
                if (!$row['ok']) {
                    $errors[] = 'مهاجرت ' . $row['file'] . ' شکست خورد: ' . $row['error'];
                }
            }

            if ($errors === []) {
                @file_put_contents(LOCK_FILE, date('c') . "\n");
                $_SESSION['install_done'] = true;
                header('Location: ?step=done');
                exit;
            }
        }
    }
    $step = 'config';
}

// ─── گام ۳: پایان ──────────────────────────────────────────────────
if ($step === 'done') {
    render_done();
    exit;
}

// ─── گام ۱: بررسی محیط ─────────────────────────────────────────────
$_SESSION['install_csrf'] ??= bin2hex(random_bytes(32));

$checks = environment_checks();
$blocked = false;
foreach ($checks as $rows) {
    foreach ($rows as $row) {
        if ($row['status'] === 'fail') {
            $blocked = true;
        }
    }
}

if ($step === 'config' && !$blocked) {
    render_config_form($errors, $_SESSION['install_csrf']);
    exit;
}

render_shell(
    'نصب رشن — بررسی محیط',
    $checks,
    $blocked ? null : '?step=config',
    $errors
);

// ═══════════════════════════════════════════════════════════════════
// توابع
// ═══════════════════════════════════════════════════════════════════

/**
 * بررسی‌هایی که پیش از داشتن .env هم ممکن‌اند.
 *
 * عمداً HealthCheck برنامه را صدا نمی‌زند، چون آن به Config و DB نیاز
 * دارد که هنوز وجود ندارند. بعد از نصب، صفحهٔ doctor کامل‌تر را ببینید.
 *
 * @return array<string,array<int,array{label:string,status:string,value:string,hint:string}>>
 */
function environment_checks(): array
{
    $phpOk = version_compare(PHP_VERSION, '8.1', '>=');

    $extensions = [];
    foreach ([
        'pdo_mysql' => [true,  'اتصال به دیتابیس. بدون این هیچ‌چیز کار نمی‌کند.'],
        'mbstring'  => [true,  'کار با متن فارسی.'],
        'json'      => [true,  'پایهٔ همهٔ APIها.'],
        'curl'      => [true,  'پیامک کاوه‌نگار و درگاه پرداخت.'],
        'openssl'   => [true,  'ساخت کلید و توکن امن.'],
        'soap'      => [false, 'پیامک ملی‌پیامک با SOAP کار می‌کند. اگر نباشد، کاوه‌نگار را ارائه‌دهندهٔ اصلی کنید.'],
        'gd'        => [false, 'تغییر اندازهٔ عکس مشتری.'],
        'zip'       => [false, 'خروجی اکسل گزارش‌ها.'],
    ] as $name => [$required, $why]) {
        $has = extension_loaded($name);
        $extensions[] = [
            'label' => $name . ($required ? ' (ضروری)' : ' (اختیاری)'),
            'status' => $has ? 'ok' : ($required ? 'fail' : 'warn'),
            'value' => $has ? 'فعال' : 'غایب',
            'hint' => $has ? '' : $why,
        ];
    }

    $paths = [];
    foreach ([
        'storage' => 'فایل قفل نصب و لاگ‌ها',
        'storage/logs' => 'لاگ خطا و پیامک',
        'storage/uploads/customer_photos' => 'عکس مشتری',
    ] as $relative => $why) {
        $full = RESHEN_ROOT . '/' . $relative;
        if (!is_dir($full)) {
            @mkdir($full, 0o755, true);
        }
        $writable = is_dir($full) && is_writable($full);
        $paths[] = [
            'label' => $relative,
            'status' => $writable ? 'ok' : 'fail',
            'value' => $writable ? 'قابل نوشتن' : (is_dir($full) ? 'فقط خواندنی' : 'ساخته نشد'),
            'hint' => $writable ? '' : "برای {$why} لازم است. از File Manager در cPanel، دسترسی پوشه را ۷۵۵ کنید.",
        ];
    }

    $envPath = RESHEN_ROOT . '/.env';
    $envWritable = is_file($envPath) ? is_writable($envPath) : is_writable(RESHEN_ROOT);
    $paths[] = [
        'label' => 'فایل .env',
        'status' => $envWritable ? 'ok' : 'fail',
        'value' => is_file($envPath) ? 'هست' : 'ساخته می‌شود',
        'hint' => $envWritable ? '' : 'پوشهٔ اصلی پروژه قابل نوشتن نیست. دسترسی را ۷۵۵ کنید.',
    ];

    return [
        'PHP' => [[
            'label' => 'نسخهٔ PHP',
            'status' => $phpOk ? 'ok' : 'fail',
            'value' => PHP_VERSION,
            'hint' => $phpOk ? '' : 'رشن دست‌کم PHP 8.1 می‌خواهد. در cPanel از «Select PHP Version» عوضش کنید.',
        ]],
        'افزونه‌های PHP' => $extensions,
        'پوشه‌ها و دسترسی‌ها' => $paths,
    ];
}

/** @return true|string true یعنی موفق، وگرنه پیام خطا */
function write_env(array $db, string $siteUrl): true|string
{
    $examplePath = RESHEN_ROOT . '/.env.example';
    $env = is_file($examplePath) ? (string) file_get_contents($examplePath) : '';

    $values = [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_KEY' => bin2hex(random_bytes(32)),
        'APP_URL' => $siteUrl !== '' ? $siteUrl : detected_url(),
        'DB_HOST' => $db['host'],
        'DB_PORT' => $db['port'],
        'DB_DATABASE' => $db['name'],
        'DB_USERNAME' => $db['user'],
        'DB_PASSWORD' => $db['pass'],
    ];

    foreach ($values as $key => $value) {
        // مقدارهایی که فاصله یا # دارند باید داخل گیومه بروند.
        $quoted = preg_match('/[\s#"\']/', $value) === 1
            ? '"' . str_replace('"', '\"', $value) . '"'
            : $value;

        $line = $key . '=' . $quoted;
        $env = preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $env) === 1
            ? (string) preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $env)
            : rtrim($env, "\n") . "\n" . $line . "\n";
    }

    $ok = @file_put_contents(RESHEN_ROOT . '/.env', $env);

    if ($ok === false) {
        return 'فایل .env نوشته نشد. دسترسی پوشهٔ اصلی پروژه را بررسی کنید.';
    }

    @chmod(RESHEN_ROOT . '/.env', 0o600);

    return true;
}

function detected_url(): string
{
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // install.php داخل public/ است؛ ریشهٔ سایت یک پله بالاتر از آن نیست،
    // چون .htaccess ریشه «/public» را پنهان می‌کند.
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if (str_ends_with($dir, '/public')) {
        $dir = substr($dir, 0, -strlen('/public'));
    }

    return $scheme . '://' . $host . $dir;
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function render_config_form(array $errors, string $csrf): void
{
    $guess = detected_url();
    ?>
    <?php render_head('نصب رشن — اتصال دیتابیس'); ?>
    <div class="wrap">
      <h1>رشن</h1>
      <p class="sub">گام ۲ از ۳ — اتصال به دیتابیس</p>

      <?php foreach ($errors as $error): ?>
        <div class="alert"><?= h($error) ?></div>
      <?php endforeach; ?>

      <div class="card">
        <p class="note">
          در cPanel، از بخش <strong>MySQL Databases</strong> یک دیتابیس و یک کاربر بسازید،
          کاربر را به دیتابیس اضافه کنید و همهٔ دسترسی‌ها را بدهید. معمولاً نام‌ها پیشوند
          حساب شما را دارند، مثل <code>myuser_reshen</code>.
        </p>

        <form method="post">
          <input type="hidden" name="_step" value="config">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

          <label>میزبان دیتابیس
            <input name="db_host" value="localhost" dir="ltr" required>
            <small>در اکثر هاست‌های cPanel همان <code>localhost</code> است.</small>
          </label>

          <label>پورت
            <input name="db_port" value="3306" dir="ltr" required>
          </label>

          <label>نام دیتابیس
            <input name="db_name" dir="ltr" required placeholder="myuser_reshen">
          </label>

          <label>نام کاربری دیتابیس
            <input name="db_user" dir="ltr" required placeholder="myuser_reshen">
          </label>

          <label>رمز عبور دیتابیس
            <input name="db_pass" type="password" dir="ltr">
          </label>

          <label>آدرس سایت
            <input name="app_url" value="<?= h($guess) ?>" dir="ltr">
            <small>اگر درست حدس زده شده، دست نزنید.</small>
          </label>

          <button type="submit">ساخت جدول‌ها و پایان نصب</button>
        </form>
      </div>
    </div>
    <?php render_foot();
}

function render_done(): void
{
    ?>
    <?php render_head('نصب رشن — تمام شد'); ?>
    <div class="wrap">
      <h1>رشن</h1>
      <p class="sub">گام ۳ از ۳ — نصب کامل شد</p>

      <div class="verdict ok">نصب با موفقیت انجام شد.</div>

      <div class="card">
        <h2>حالا چه کنید</h2>
        <ol>
          <li><strong>وارد شوید.</strong> صفحهٔ ورود را باز کنید و شمارهٔ موبایل خودتان را بزنید.
              اولین کاربری که ثبت‌نام کند، صاحب سالن می‌شود.</li>
          <li><strong>پیامک را تنظیم کنید.</strong> تا وقتی <code>SMS_DRIVER</code> در فایل
              <code>.env</code> روی <code>log</code> باشد، هیچ پیامکی ارسال نمی‌شود و کد ورود
              فقط در <code>storage/logs/sms.log</code> نوشته می‌شود. برای سالن واقعی باید
              ملی‌پیامک یا کاوه‌نگار را تنظیم کنید.</li>
          <li><strong>الگوی پیامک را ثبت کنید.</strong> کد ورود روی خط خدماتی مشترک با متن آزاد
              ارسال نمی‌شود. تأیید الگو چند روز طول می‌کشد — همین امروز شروع کنید.</li>
          <li><strong>کرون را فعال کنید.</strong> بدون آن، یادآورها و پیامک‌های صف ارسال
              نمی‌شوند. راهنمایش در <code>docs/40-deploy/01-cpanel.md</code> است.</li>
        </ol>

        <p class="note">
          برای اطمینان از سلامت همه‌چیز، صفحهٔ <code>doctor.php</code> را باز کنید.
        </p>

        <a class="btn" href="./">ورود به رشن</a>
      </div>

      <footer>
        فایل <code>storage/installed.lock</code> ساخته شد و این صفحه دیگر باز نمی‌شود.
        برای امنیت بیشتر می‌توانید <code>public/install.php</code> را هم پاک کنید.
      </footer>
    </div>
    <?php render_foot();
}

/**
 * @param array<string,array<int,array{label:string,status:string,value:string,hint:string}>> $groups
 */
function render_shell(string $title, array $groups, ?string $nextUrl, array $errors = []): void
{
    $failures = 0;
    foreach ($groups as $rows) {
        foreach ($rows as $row) {
            if ($row['status'] === 'fail') {
                $failures++;
            }
        }
    }
    ?>
    <?php render_head($title); ?>
    <div class="wrap">
      <h1>رشن</h1>
      <p class="sub"><?= h($title) ?></p>

      <?php foreach ($errors as $error): ?>
        <div class="alert"><?= h($error) ?></div>
      <?php endforeach; ?>

      <div class="verdict <?= $failures === 0 ? 'ok' : 'bad' ?>">
        <?= $failures === 0
            ? 'این هاست آمادهٔ نصب است.'
            : $failures . ' مورد باید پیش از نصب درست شود (پایین با ✗ مشخص‌اند).' ?>
      </div>

      <?php foreach ($groups as $group => $rows): ?>
        <h2><?= h((string) $group) ?></h2>
        <div class="card rows">
          <?php foreach ($rows as $row): ?>
            <div class="row">
              <span class="mark <?= h($row['status']) ?>">
                <?= $row['status'] === 'ok' ? '✓' : ($row['status'] === 'warn' ? '!' : '✗') ?>
              </span>
              <span class="lab"><?= h($row['label']) ?></span>
              <span class="val"><?= h($row['value']) ?></span>
              <?php if ($row['hint'] !== ''): ?>
                <span class="hint"><?= h($row['hint']) ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <?php if ($nextUrl !== null): ?>
        <a class="btn" href="<?= h($nextUrl) ?>">ادامه — اتصال دیتابیس</a>
      <?php else: ?>
        <p class="note">پس از رفع موارد بالا، این صفحه را تازه کنید.</p>
      <?php endif; ?>
    </div>
    <?php render_foot();
}

function render_head(string $title): void
{
    ?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?></title>
<style>
  :root{--bg:#f6f7f9;--card:#fff;--ink:#1c1f24;--dim:#6b7280;--line:#e5e7eb;
        --ok:#15803d;--okbg:#f0fdf4;--bad:#b91c1c;--badbg:#fef2f2;--warn:#a16207;
        --brand:#2563eb}
  @media (prefers-color-scheme:dark){
    :root{--bg:#111316;--card:#1a1d21;--ink:#e8eaed;--dim:#9aa0a6;--line:#2a2e33;
          --ok:#4ade80;--okbg:#0f2417;--bad:#f87171;--badbg:#2a1416;--warn:#fbbf24}
  }
  *{box-sizing:border-box}
  body{margin:0;padding:16px;background:var(--bg);color:var(--ink);
       font:15px/1.8 Tahoma,system-ui,sans-serif}
  .wrap{max-width:720px;margin:0 auto}
  h1{font-size:30px;margin:12px 0 2px;color:var(--brand)}
  .sub{color:var(--dim);font-size:14px;margin:0 0 20px}
  h2{font-size:14px;margin:22px 0 8px;color:var(--dim)}
  .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:18px}
  .card.rows{padding:0;overflow:hidden}
  .verdict{padding:13px 16px;border-radius:10px;margin:0 0 18px;font-weight:700}
  .verdict.ok{background:var(--okbg);color:var(--ok)}
  .verdict.bad{background:var(--badbg);color:var(--bad)}
  .alert{background:var(--badbg);color:var(--bad);padding:12px 14px;border-radius:10px;
         margin:0 0 14px;font-size:14px}
  .row{display:flex;gap:10px;padding:11px 14px;border-bottom:1px solid var(--line);
       align-items:flex-start;flex-wrap:wrap}
  .row:last-child{border-bottom:0}
  .mark{flex:0 0 auto;width:18px;font-weight:700}
  .mark.ok{color:var(--ok)} .mark.fail{color:var(--bad)} .mark.warn{color:var(--warn)}
  .lab{flex:1 1 170px;font-weight:600;min-width:0}
  .val{flex:0 0 auto;color:var(--dim);font-family:monospace;direction:ltr;unicode-bidi:plaintext}
  .hint{flex:1 1 100%;color:var(--dim);font-size:13px;padding-inline-start:28px}
  label{display:block;margin:0 0 14px;font-weight:600;font-size:14px}
  input{width:100%;margin-top:6px;padding:10px 12px;border:1px solid var(--line);
        border-radius:9px;background:var(--bg);color:var(--ink);font:inherit}
  small{display:block;margin-top:5px;color:var(--dim);font-weight:400;font-size:12.5px}
  button,.btn{display:inline-block;width:100%;text-align:center;margin-top:8px;padding:12px;
              background:var(--brand);color:#fff;border:0;border-radius:10px;
              font:inherit;font-weight:700;cursor:pointer;text-decoration:none}
  code{background:var(--bg);padding:1px 5px;border-radius:5px;font-size:13px;
       direction:ltr;unicode-bidi:plaintext;display:inline-block}
  ol{padding-inline-start:20px;margin:10px 0} li{margin-bottom:10px}
  .note{color:var(--dim);font-size:13.5px;margin:0 0 16px}
  footer{margin:20px 0;color:var(--dim);font-size:13px;text-align:center}
</style>
</head>
<body>
<?php }

function render_foot(): void
{
    echo "</body>\n</html>\n";
}
