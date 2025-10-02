# CRM Digital Marketing

Hệ thống CRM đơn giản cho Digital Marketing với PHP, SQLite và Docker.

## Tính năng

- 👥 Quản lý liên hệ (contacts) với UTM tracking
- 🤝 Quản lý giao dịch (deals) với pipeline
- 📈 Quản lý chiến dịch marketing 
- 📋 Quản lý công việc (tasks)
- 📊 Báo cáo hiệu quả marketing
- 🔒 Hệ thống đăng nhập bảo mật với CSRF protection

## Cài đặt và chạy

### 1. Clone repository
```bash
git clone <repository-url>
cd crm-digital_marketing
```

### 2. Chạy bằng Docker
```bash
# Build và start containers
docker-compose up -d --build

# Kiểm tra containers đang chạy
docker-compose ps
```

### 3. Khởi tạo database
Mở trình duyệt và truy cập:
```
http://localhost:8080/setup.php
```

### 4. Truy cập ứng dụng
```
URL: http://localhost:8080/src/index.php
Username: admin
Password: admin123
```

## Cấu trúc dự án

```
├── docker-compose.yml      # Docker configuration
├── Dockerfile             # PHP container setup
├── nginx.conf             # Nginx configuration
├── setup.php              # Database initialization script
└── src/
    ├── index.php          # Main application router
    ├── crm.sqlite         # SQLite database file
    ├── lib/
    │   ├── db.php         # Database connection & helper functions
    │   └── auth.php       # Authentication functions
    └── views/
        ├── layout.php     # HTML layout & CSS
        ├── dashboard.php  # Dashboard view
        ├── contacts.php   # Contacts management
        ├── deals.php      # Deals management
        ├── activities.php # Activities tracking
        ├── campaigns.php  # Campaign management
        ├── tasks.php      # Task management
        └── reports.php    # Reports & analytics
```

## Services

- **Web Server**: Nginx (Port 8080)
- **PHP Application**: PHP 8.2-FPM với SQLite
- **Database Management**: phpMyAdmin (Port 8081)
- **Database**: MariaDB (Port 3306) - dự phòng, hiện tại dùng SQLite

## Troubleshooting

### Lỗi permission
```bash
docker-compose exec app chown -R www-data:www-data /var/www/html
```

### Rebuild containers
```bash
docker-compose down
docker-compose up -d --build
```

### Xem logs
```bash
docker-compose logs app
docker-compose logs web
```

## Development

Để phát triển thêm tính năng:

1. Edit file trong thư mục `src/`
2. Refresh browser để xem thay đổi
3. Sử dụng phpMyAdmin để quản lý database nếu cần

## API Endpoints

- `GET /src/index.php?action=api&what=contacts` - Lấy danh sách contacts (JSON)
- `GET /src/index.php?action=api&what=deals` - Lấy danh sách deals (JSON)

## Bảo mật

- CSRF protection cho tất cả forms
- Password hashing với PHP `password_hash()`
- HTML escaping cho tất cả output
- Prepared statements cho database queries
