-- HP COMPUTER
-- Migration: Tao cau truc kiem ke kho
-- Date: 2026-11-20
-- Source: phpMyAdmin structure export of ezyro_43436024_hpcomputer
-- Prerequisites: nguoi_dung(ma_nguoi_dung), san_pham(ma_san_pham)
-- Important: This script does not change existing stock or insert demo data.
-- Execute on a separate TEST database first. IF NOT EXISTS does not repair
-- an existing table with an incomplete/different structure.

CREATE TABLE IF NOT EXISTS `phieu_kiem_ke` (
    `ma_phieu_kiem_ke` INT(11) NOT NULL AUTO_INCREMENT,
    `ma_so_phieu` VARCHAR(50) NOT NULL,
    `ma_nguoi_lap` INT(11) NOT NULL,
    `ma_nguoi_duyet` INT(11) DEFAULT NULL,
    `ngay_kiem_ke` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `trang_thai` ENUM('DRAFT', 'PENDING', 'APPROVED', 'REJECTED')
        NOT NULL DEFAULT 'DRAFT',
    `ly_do` TEXT DEFAULT NULL,
    `ngay_duyet` DATETIME DEFAULT NULL,
    `ngay_tao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ngay_cap_nhat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`ma_phieu_kiem_ke`),
    UNIQUE KEY `uq_ma_so_phieu_kiem_ke` (`ma_so_phieu`),
    KEY `idx_kiem_ke_trang_thai` (`trang_thai`),
    KEY `fk_kk_nguoi_lap` (`ma_nguoi_lap`),
    KEY `fk_kk_nguoi_duyet` (`ma_nguoi_duyet`),

    CONSTRAINT `fk_kk_nguoi_lap`
        FOREIGN KEY (`ma_nguoi_lap`)
        REFERENCES `nguoi_dung` (`ma_nguoi_dung`),
    CONSTRAINT `fk_kk_nguoi_duyet`
        FOREIGN KEY (`ma_nguoi_duyet`)
        REFERENCES `nguoi_dung` (`ma_nguoi_dung`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chi_tiet_phieu_kiem_ke` (
    `ma_chi_tiet_kiem_ke` INT(11) NOT NULL AUTO_INCREMENT,
    `ma_phieu_kiem_ke` INT(11) NOT NULL,
    `ma_san_pham` INT(11) NOT NULL,
    `so_luong_he_thong` INT(10) UNSIGNED NOT NULL,
    `so_luong_thuc_te` INT(10) UNSIGNED NOT NULL,

    PRIMARY KEY (`ma_chi_tiet_kiem_ke`),
    UNIQUE KEY `uq_kk_san_pham` (`ma_phieu_kiem_ke`, `ma_san_pham`),
    KEY `idx_kk_san_pham` (`ma_san_pham`),

    CONSTRAINT `fk_ctkk_phieu`
        FOREIGN KEY (`ma_phieu_kiem_ke`)
        REFERENCES `phieu_kiem_ke` (`ma_phieu_kiem_ke`),
    CONSTRAINT `fk_ctkk_san_pham`
        FOREIGN KEY (`ma_san_pham`)
        REFERENCES `san_pham` (`ma_san_pham`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Permissions (quyen/vai_tro_quyen) are not modified here.
-- The source database already has inventory.stocktake.approve granted to Admin.
-- Export/seed permission data separately when deploying to a fresh database.
