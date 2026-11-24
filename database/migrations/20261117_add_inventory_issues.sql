
-- HP COMPUTER
-- Migration: Them chuc nang quan ly xuat kho
-- Ngay tao: 2026-11-17
-- Yeu cau: Cac bang nguoi_dung, don_hang,
-- san_pham, lich_su_kho da ton tai.

-- 1. Tao bang phieu xuat kho
CREATE TABLE IF NOT EXISTS phieu_xuat (
    ma_phieu_xuat INT(11) NOT NULL AUTO_INCREMENT,
    ma_so_phieu VARCHAR(50) NOT NULL,
    ma_nguoi_dung INT(11) NOT NULL,
    ma_don_hang INT(11) DEFAULT NULL,

    loai_xuat ENUM(
        'BAN_HANG',
        'LAP_RAP',
        'BAO_HANH',
        'DIEU_CHUYEN',
        'KHAC'
    ) NOT NULL DEFAULT 'KHAC',

    ngay_xuat DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    trang_thai ENUM(
        'DRAFT',
        'PENDING',
        'CONFIRMED',
        'CANCELLED'
    ) NOT NULL DEFAULT 'DRAFT',

    ghi_chu TEXT DEFAULT NULL,

    ngay_tao DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    ngay_cap_nhat DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (ma_phieu_xuat),
    UNIQUE KEY uq_ma_so_phieu (ma_so_phieu),
    KEY idx_px_trang_thai (trang_thai),
    KEY idx_px_don_hang (ma_don_hang),

    CONSTRAINT fk_px_nguoi_dung
        FOREIGN KEY (ma_nguoi_dung)
        REFERENCES nguoi_dung(ma_nguoi_dung),

    CONSTRAINT fk_px_don_hang
        FOREIGN KEY (ma_don_hang)
        REFERENCES don_hang(ma_don_hang)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- 2. Tao bang chi tiet phieu xuat
CREATE TABLE IF NOT EXISTS chi_tiet_phieu_xuat (
    ma_chi_tiet_phieu_xuat INT(11)
        NOT NULL AUTO_INCREMENT,

    ma_phieu_xuat INT(11) NOT NULL,
    ma_san_pham INT(11) NOT NULL,
    so_luong INT(10) UNSIGNED NOT NULL,

    PRIMARY KEY (ma_chi_tiet_phieu_xuat),

    UNIQUE KEY uq_px_san_pham (
        ma_phieu_xuat,
        ma_san_pham
    ),

    KEY idx_ctpx_san_pham (ma_san_pham),

    CONSTRAINT fk_ctpx_phieu_xuat
        FOREIGN KEY (ma_phieu_xuat)
        REFERENCES phieu_xuat(ma_phieu_xuat),

    CONSTRAINT fk_ctpx_san_pham
        FOREIGN KEY (ma_san_pham)
        REFERENCES san_pham(ma_san_pham)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- 3. Bo sung chung tu xuat kho vao lich su
ALTER TABLE lich_su_kho
MODIFY COLUMN loai_chung_tu ENUM(
    'PHIEU_NHAP',
    'DON_HANG',
    'DIEU_CHINH',
    'TRA_HANG',
    'PHIEU_XUAT'
) NOT NULL;
