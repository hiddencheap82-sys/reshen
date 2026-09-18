.PHONY: help setup up down fresh test lint stan check doctor queue

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

setup:  ## نصب اولیه
	composer install
	npm install
	cp -n .env.example .env || true
	php artisan key:generate
	php artisan migrate --seed
	npm run build

up:     ## بالا آوردن محیط توسعه
	docker compose up -d

down:   ## خواباندن محیط
	docker compose down

fresh:  ## دیتابیس تازه با دادهٔ نمونه
	php artisan migrate:fresh --seed

test:   ## اجرای تست‌ها
	./vendor/bin/pest

lint:   ## قالب‌بندی کد
	./vendor/bin/pint

stan:   ## تحلیل ایستا
	./vendor/bin/phpstan analyse --memory-limit=1G

check:  ## همه‌چیز پیش از پوش
	$(MAKE) lint && $(MAKE) stan && $(MAKE) test

doctor: ## بررسی آمادگی محیط (به‌ویژه php-soap برای پیامک)
	php artisan reshen:doctor

queue:  ## اجرای worker صف
	php artisan queue:work --tries=3 --timeout=60
