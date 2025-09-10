./
├─ app/
│ ├─ Controllers/
│ │ ├─ HomeController.php
│ │ └─ OperationController.php
│ ├─ Middlewares/
│ │ ├─ AuthMiddleware.php
│ │ ├─ CORSMiddleware.php
│ │ └─ SecurityHeadersMiddleware.php
│ ├─ Models/
│ │ ├─ Operation.php
│ │ ├─ OperationHistory.php
│ │ └─ User.php
│ ├─ Repositories/
│ │ ├─ OperationHistoryRepository.php
│ │ └─ OperationRepository.php
│ └─ Views/
│ ├─ layout/
│ │ ├─ footer.php
│ │ └─ header.php
│ ├─ home/index.php
│ └─ operations/
│ ├─ index.php
│ └─ show.php
├─ core/
│ ├─ Controller.php
│ ├─ Database.php
│ ├─ Env.php
│ ├─ Middleware.php
│ ├─ Response.php
│ └─ Router.php
├─ database/
│ ├─ migrations/
│ │ ├─ 2025_09_10_000001_create_users.sql
│ │ ├─ 2025_09_10_000002_create_operations.sql
│ │ └─ 2025_09_10_000003_create_operation_history.sql
│ └─ seeds/
│ └─ seed_operations.sql
├─ docs/
│ └─ INSTALL.md
├─ public/
│ ├─ .htaccess
│ └─ index.php
├─ docker/
│ └─ compose.yaml
├─ tests/
│ └─ OperationRepositoryTest.php
├─ .env.example
├─ composer.json
├─ phpcs.xml
└─ phpunit.xml