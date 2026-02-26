-- MySQL schema for WebHoaTetAI
-- Create database manually, then select it before running these statements.

CREATE TABLE khach_hang (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ten VARCHAR(200) NOT NULL,
  dia_chi VARCHAR(255) DEFAULT '',
  sdt VARCHAR(50) DEFAULT '',
  coc DECIMAL(12,2) NOT NULL DEFAULT 0,
  trang_thai_boc VARCHAR(20) NOT NULL DEFAULT 'chua_boc',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE loai_hoa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ten VARCHAR(200) NOT NULL UNIQUE,
  so_luong_ban_dau DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE khach_hang_hoa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  khach_hang_id INT NOT NULL,
  loai_hoa_id INT NOT NULL,
  so_luong DECIMAL(10,2) NOT NULL DEFAULT 0,
  gia DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_khach_hang FOREIGN KEY (khach_hang_id) REFERENCES khach_hang(id) ON DELETE CASCADE,
  CONSTRAINT fk_loai_hoa FOREIGN KEY (loai_hoa_id) REFERENCES loai_hoa(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE khach_hang_hoa_thuc_te (
  id INT AUTO_INCREMENT PRIMARY KEY,
  khach_hang_id INT NOT NULL,
  loai_hoa_id INT NOT NULL,
  so_luong DECIMAL(10,2) NOT NULL DEFAULT 0,
  gia DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_kh_thuc_te FOREIGN KEY (khach_hang_id) REFERENCES khach_hang(id) ON DELETE CASCADE,
  CONSTRAINT fk_lh_thuc_te FOREIGN KEY (loai_hoa_id) REFERENCES loai_hoa(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE admin_user (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE nhap_vuon_ngoai (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loai_hoa_id INT NOT NULL,
  so_luong_cap DECIMAL(10,2) NOT NULL DEFAULT 0,
  don_gia_lay DECIMAL(12,2) NOT NULL DEFAULT 0,
  ten_nha_vuon VARCHAR(200) NOT NULL,
  ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
  ngay_nhap DATE NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_nhap_vuon_ngoai_loai_hoa FOREIGN KEY (loai_hoa_id) REFERENCES loai_hoa(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
