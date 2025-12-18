.PHONY: up serve dev

# run container when database not running
up: 
	@if [ $$(docker-compose ps --filter "status=running" --format "{{.Service}}" | grep -w "postgres" | wc -l) -eq 0 ]; then \
		echo "🚀 Starting postgres container..."; \
		docker-compose up -d; \
	else \
		echo "✅ Postgres is already running."; \
	fi

#running laravel
serve:
	@echo "🌐 Starting Laravel..."
	@php artisan serve

#runing development server
dev: up serve