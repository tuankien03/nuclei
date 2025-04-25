-- Tạo database nếu chưa tồn tại
CREATE DATABASE IF NOT EXISTS wordpress60;
CREATE DATABASE IF NOT EXISTS wordpress51;

-- Cấp quyền cho user 'wordpress' (đã khai báo trong docker-compose)
GRANT ALL PRIVILEGES ON wordpress60.* TO 'wordpress'@'%';
GRANT ALL PRIVILEGES ON wordpress51.* TO 'wordpress'@'%';

-- Áp dụng quyền
FLUSH PRIVILEGES;
