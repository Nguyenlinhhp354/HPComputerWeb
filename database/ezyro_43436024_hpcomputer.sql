-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: sql208.ezyro.com
-- Thời gian đã tạo: Th10 15, 2026 lúc 10:01 PM
-- Phiên bản máy phục vụ: 11.4.13-MariaDB
-- Phiên bản PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `ezyro_43436024_hpcomputer`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chi_tiet_don_hang`
--

CREATE TABLE `chi_tiet_don_hang` (
  `ma_chi_tiet_don_hang` int(11) NOT NULL,
  `ma_don_hang` int(11) NOT NULL,
  `ma_san_pham` int(11) NOT NULL,
  `ten_san_pham` varchar(255) NOT NULL,
  `so_luong` int(10) UNSIGNED NOT NULL,
  `don_gia` decimal(15,2) UNSIGNED NOT NULL,
  `tien_giam_gia` decimal(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `thanh_tien` decimal(15,2) UNSIGNED NOT NULL,
  `thoi_han_bao_hanh` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chi_tiet_gio_hang`
--

CREATE TABLE `chi_tiet_gio_hang` (
  `ma_chi_tiet_gio_hang` int(11) NOT NULL,
  `ma_gio_hang` int(11) NOT NULL,
  `ma_san_pham` int(11) NOT NULL,
  `so_luong` int(10) UNSIGNED NOT NULL,
  `ngay_them` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chi_tiet_phieu_nhap`
--

CREATE TABLE `chi_tiet_phieu_nhap` (
  `ma_chi_tiet_phieu_nhap` int(11) NOT NULL,
  `ma_phieu_nhap` int(11) NOT NULL,
  `ma_san_pham` int(11) NOT NULL,
  `so_luong` int(10) UNSIGNED NOT NULL,
  `don_gia_nhap` decimal(15,2) UNSIGNED NOT NULL,
  `thanh_tien` decimal(15,2) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chuong_trinh_khuyen_mai`
--

CREATE TABLE `chuong_trinh_khuyen_mai` (
  `ma_chuong_trinh` int(11) NOT NULL,
  `ten_chuong_trinh` varchar(200) NOT NULL,
  `mo_ta` text DEFAULT NULL,
  `loai_giam_gia` enum('PERCENT','FIXED_AMOUNT') NOT NULL,
  `gia_tri_giam` decimal(15,2) UNSIGNED NOT NULL,
  `gia_tri_giam_toi_da` decimal(15,2) UNSIGNED DEFAULT NULL,
  `ngay_bat_dau` datetime NOT NULL,
  `ngay_ket_thuc` datetime NOT NULL,
  `trang_thai` enum('DRAFT','ACTIVE','INACTIVE','EXPIRED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `ma_nguoi_tao` int(11) DEFAULT NULL,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `danh_muc`
--

CREATE TABLE `danh_muc` (
  `ma_danh_muc` int(11) NOT NULL,
  `ten_danh_muc` varchar(150) NOT NULL,
  `ma_danh_muc_cha` int(11) DEFAULT NULL,
  `mo_ta` text DEFAULT NULL,
  `trang_thai` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `don_hang`
--

CREATE TABLE `don_hang` (
  `ma_don_hang` int(11) NOT NULL,
  `ma_khach_hang` int(11) NOT NULL,
  `ma_nguoi_tao` int(11) DEFAULT NULL,
  `ma_so_don_hang` varchar(50) NOT NULL,
  `ngay_dat` datetime NOT NULL DEFAULT current_timestamp(),
  `ten_nguoi_nhan` varchar(150) NOT NULL,
  `sdt_nguoi_nhan` varchar(20) NOT NULL,
  `dia_chi_giao_hang` varchar(255) NOT NULL,
  `tam_tinh` decimal(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `tien_giam_gia` decimal(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `phi_van_chuyen` decimal(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `tong_tien` decimal(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `trang_thai_don_hang` enum('PENDING','CONFIRMED','PROCESSING','SHIPPING','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `trang_thai_thanh_toan` enum('UNPAID','PARTIAL','PAID','REFUNDED') NOT NULL DEFAULT 'UNPAID',
  `ghi_chu` text DEFAULT NULL,
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `gia_tri_thuoc_tinh`
--

CREATE TABLE `gia_tri_thuoc_tinh` (
  `ma_san_pham` int(11) NOT NULL,
  `ma_thuoc_tinh` int(11) NOT NULL,
  `gia_tri` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `gio_hang`
--

CREATE TABLE `gio_hang` (
  `ma_gio_hang` int(11) NOT NULL,
  `ma_khach_hang` int(11) NOT NULL,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `hinh_anh_san_pham`
--

CREATE TABLE `hinh_anh_san_pham` (
  `ma_hinh_anh` int(11) NOT NULL,
  `ma_san_pham` int(11) NOT NULL,
  `duong_dan_anh` varchar(500) NOT NULL,
  `la_anh_dai_dien` tinyint(1) NOT NULL DEFAULT 0,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khach_hang`
--

CREATE TABLE `khach_hang` (
  `ma_khach_hang` int(11) NOT NULL,
  `ma_nguoi_dung` int(11) DEFAULT NULL,
  `ten_khach_hang` varchar(150) NOT NULL,
  `so_dien_thoai` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `dia_chi` varchar(255) DEFAULT NULL,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khuyen_mai_don_hang`
--

CREATE TABLE `khuyen_mai_don_hang` (
  `ma_khuyen_mai_don_hang` int(11) NOT NULL,
  `ma_don_hang` int(11) NOT NULL,
  `ma_chuong_trinh` int(11) NOT NULL,
  `so_tien_giam` decimal(15,2) UNSIGNED NOT NULL,
  `ngay_ap_dung` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lich_su_bao_hanh`
--

CREATE TABLE `lich_su_bao_hanh` (
  `ma_lich_su_bao_hanh` int(11) NOT NULL,
  `ma_phieu_bao_hanh` int(11) NOT NULL,
  `ma_nguoi_thuc_hien` int(11) DEFAULT NULL,
  `trang_thai_cu` enum('RECEIVED','CHECKING','REPAIRING','WAITING_PARTS','COMPLETED','REJECTED','RETURNED') DEFAULT NULL,
  `trang_thai_moi` enum('RECEIVED','CHECKING','REPAIRING','WAITING_PARTS','COMPLETED','REJECTED','RETURNED') NOT NULL,
  `noi_dung_xu_ly` text DEFAULT NULL,
  `ngay_thuc_hien` datetime NOT NULL DEFAULT current_timestamp(),
  `ghi_chu` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lich_su_kho`
--

CREATE TABLE `lich_su_kho` (
  `ma_lich_su_kho` int(11) NOT NULL,
  `ma_san_pham` int(11) NOT NULL,
  `ma_nguoi_dung` int(11) NOT NULL,
  `loai_bien_dong` enum('IMPORT','EXPORT','ADJUSTMENT','RETURN') NOT NULL,
  `huong_bien_dong` enum('TANG','GIAM') NOT NULL,
  `so_luong` int(10) UNSIGNED NOT NULL,
  `loai_chung_tu` enum('PHIEU_NHAP','DON_HANG','DIEU_CHINH','TRA_HANG') NOT NULL,
  `ma_chung_tu` int(11) DEFAULT NULL,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp(),
  `ghi_chu` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lich_su_trang_thai_don_hang`
--

CREATE TABLE `lich_su_trang_thai_don_hang` (
  `ma_lich_su_trang_thai` int(11) NOT NULL,
  `ma_don_hang` int(11) NOT NULL,
  `ma_nguoi_thuc_hien` int(11) DEFAULT NULL,
  `trang_thai_cu` enum('PENDING','CONFIRMED','PROCESSING','SHIPPING','COMPLETED','CANCELLED') DEFAULT NULL,
  `trang_thai_moi` enum('PENDING','CONFIRMED','PROCESSING','SHIPPING','COMPLETED','CANCELLED') NOT NULL,
  `ly_do_thay_doi` text DEFAULT NULL,
  `ngay_thay_doi` datetime NOT NULL DEFAULT current_timestamp(),
  `ghi_chu` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nguoi_dung`
--

CREATE TABLE `nguoi_dung` (
  `ma_nguoi_dung` int(11) NOT NULL,
  `ma_vai_tro` int(11) NOT NULL,
  `ten_dang_nhap` varchar(100) NOT NULL,
  `mat_khau` varchar(255) NOT NULL,
  `ho_ten` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `so_dien_thoai` varchar(20) DEFAULT NULL,
  `trang_thai` enum('ACTIVE','INACTIVE','LOCKED') NOT NULL DEFAULT 'ACTIVE',
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Đang đổ dữ liệu cho bảng `nguoi_dung`
--

INSERT INTO `nguoi_dung` (`ma_nguoi_dung`, `ma_vai_tro`, `ten_dang_nhap`, `mat_khau`, `ho_ten`, `email`, `so_dien_thoai`, `trang_thai`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(1, 1, 'admin_test', 'HpAdmin@2026!', 'Qu?n tr? viên th? nghi?m', 'admin_test@example.com', NULL, 'ACTIVE', '2026-11-15 18:58:51', '2026-11-15 18:58:51');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nhat_ky_hoat_dong`
--

CREATE TABLE `nhat_ky_hoat_dong` (
  `ma_nhat_ky` int(11) NOT NULL,
  `ma_nguoi_dung` int(11) DEFAULT NULL,
  `hanh_dong` varchar(100) NOT NULL,
  `doi_tuong` varchar(100) DEFAULT NULL,
  `ma_doi_tuong` int(11) DEFAULT NULL,
  `noi_dung` text DEFAULT NULL,
  `dia_chi_ip` varchar(45) DEFAULT NULL,
  `thiet_bi_truy_cap` varchar(255) DEFAULT NULL,
  `ngay_thuc_hien` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nha_cung_cap`
--

CREATE TABLE `nha_cung_cap` (
  `ma_nha_cung_cap` int(11) NOT NULL,
  `ten_nha_cung_cap` varchar(200) NOT NULL,
  `so_dien_thoai` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `dia_chi` varchar(255) DEFAULT NULL,
  `trang_thai` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phieu_bao_hanh`
--

CREATE TABLE `phieu_bao_hanh` (
  `ma_phieu_bao_hanh` int(11) NOT NULL,
  `ma_don_hang` int(11) NOT NULL,
  `ma_khach_hang` int(11) NOT NULL,
  `ma_san_pham` int(11) NOT NULL,
  `ma_nguoi_tiep_nhan` int(11) DEFAULT NULL,
  `ma_so_phieu` varchar(50) NOT NULL,
  `ngay_mua` date NOT NULL,
  `ngay_bat_dau_bao_hanh` date NOT NULL,
  `ngay_ket_thuc_bao_hanh` date NOT NULL,
  `ngay_tiep_nhan` datetime NOT NULL DEFAULT current_timestamp(),
  `mo_ta_loi` text NOT NULL,
  `trang_thai` enum('RECEIVED','CHECKING','REPAIRING','WAITING_PARTS','COMPLETED','REJECTED','RETURNED') NOT NULL DEFAULT 'RECEIVED',
  `ket_qua_xu_ly` text DEFAULT NULL,
  `ghi_chu` text DEFAULT NULL,
  `ngay_cap_nhat` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phieu_nhap`
--

CREATE TABLE `phieu_nhap` (
  `ma_phieu_nhap` int(11) NOT NULL,
  `ma_nha_cung_cap` int(11) NOT NULL,
  `ma_nguoi_dung` int(11) NOT NULL,
  `ma_so_phieu` varchar(50) NOT NULL,
  `ngay_nhap` datetime NOT NULL DEFAULT current_timestamp(),
  `tong_tien` decimal(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `trang_thai` enum('DRAFT','PENDING','CONFIRMED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `ghi_chu` text DEFAULT NULL,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `quyen`
--

CREATE TABLE `quyen` (
  `ma_quyen` int(11) NOT NULL,
  `ma_chuc_nang` varchar(100) NOT NULL,
  `ten_quyen` varchar(150) NOT NULL,
  `phan_he` varchar(100) NOT NULL,
  `mo_ta` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `san_pham`
--

CREATE TABLE `san_pham` (
  `ma_san_pham` int(11) NOT NULL,
  `ma_danh_muc` int(11) NOT NULL,
  `ma_thuong_hieu` int(11) DEFAULT NULL,
  `ma_sku` varchar(100) NOT NULL,
  `ten_san_pham` varchar(255) NOT NULL,
  `mo_ta` text DEFAULT NULL,
  `gia_ban` decimal(15,2) NOT NULL DEFAULT 0.00,
  `thoi_han_bao_hanh` int(11) NOT NULL DEFAULT 0,
  `trang_thai` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `san_pham_khuyen_mai`
--

CREATE TABLE `san_pham_khuyen_mai` (
  `ma_chuong_trinh` int(11) NOT NULL,
  `ma_san_pham` int(11) NOT NULL,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `serial_san_pham`
--

CREATE TABLE `serial_san_pham` (
  `ma_serial` int(11) NOT NULL,
  `ma_san_pham` int(11) NOT NULL,
  `ma_chi_tiet_phieu_nhap` int(11) DEFAULT NULL,
  `so_serial` varchar(150) NOT NULL,
  `trang_thai` enum('IN_STOCK','RESERVED','SOLD','WARRANTY','RETURNED','DAMAGED') NOT NULL DEFAULT 'IN_STOCK',
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thanh_toan`
--

CREATE TABLE `thanh_toan` (
  `ma_thanh_toan` int(11) NOT NULL,
  `ma_don_hang` int(11) NOT NULL,
  `phuong_thuc` enum('CASH','BANK_TRANSFER','COD') NOT NULL,
  `so_tien` decimal(15,2) UNSIGNED NOT NULL,
  `ma_giao_dich` varchar(150) DEFAULT NULL,
  `ngay_thanh_toan` datetime DEFAULT NULL,
  `trang_thai` enum('PENDING','SUCCESS','FAILED','CANCELLED','REFUNDED') NOT NULL DEFAULT 'PENDING',
  `ghi_chu` text DEFAULT NULL,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thuoc_tinh`
--

CREATE TABLE `thuoc_tinh` (
  `ma_thuoc_tinh` int(11) NOT NULL,
  `ten_thuoc_tinh` varchar(150) NOT NULL,
  `mo_ta` varchar(255) DEFAULT NULL,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thuong_hieu`
--

CREATE TABLE `thuong_hieu` (
  `ma_thuong_hieu` int(11) NOT NULL,
  `ten_thuong_hieu` varchar(150) NOT NULL,
  `mo_ta` text DEFAULT NULL,
  `trang_thai` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `ton_kho`
--

CREATE TABLE `ton_kho` (
  `ma_san_pham` int(11) NOT NULL,
  `so_luong` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `vai_tro`
--

CREATE TABLE `vai_tro` (
  `ma_vai_tro` int(11) NOT NULL,
  `ten_vai_tro` varchar(100) NOT NULL,
  `mo_ta` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Đang đổ dữ liệu cho bảng `vai_tro`
--

INSERT INTO `vai_tro` (`ma_vai_tro`, `ten_vai_tro`, `mo_ta`) VALUES
(1, 'Admin', 'Quan tri toan bo he thong'),
(2, 'Quan ly cua hang', 'Quan ly hoat dong kinh doanh cua hang'),
(3, 'Nhan vien ban hang', 'Tu van va xu ly don hang'),
(4, 'Nhan vien kho', 'Quan ly nhap, xuat va ton kho'),
(5, 'Nhan vien ky thuat', 'Tiep nhan va xu ly bao hanh, sua chua'),
(6, 'Khach hang', 'Nguoi mua hang tren he thong');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `vai_tro_quyen`
--

CREATE TABLE `vai_tro_quyen` (
  `ma_vai_tro` int(11) NOT NULL,
  `ma_quyen` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `chi_tiet_don_hang`
--
ALTER TABLE `chi_tiet_don_hang`
  ADD PRIMARY KEY (`ma_chi_tiet_don_hang`),
  ADD KEY `idx_chi_tiet_don_hang` (`ma_don_hang`),
  ADD KEY `idx_chi_tiet_don_hang_san_pham` (`ma_san_pham`);

--
-- Chỉ mục cho bảng `chi_tiet_gio_hang`
--
ALTER TABLE `chi_tiet_gio_hang`
  ADD PRIMARY KEY (`ma_chi_tiet_gio_hang`),
  ADD UNIQUE KEY `uq_gio_hang_san_pham` (`ma_gio_hang`,`ma_san_pham`),
  ADD KEY `idx_chi_tiet_gio_hang_san_pham` (`ma_san_pham`);

--
-- Chỉ mục cho bảng `chi_tiet_phieu_nhap`
--
ALTER TABLE `chi_tiet_phieu_nhap`
  ADD PRIMARY KEY (`ma_chi_tiet_phieu_nhap`),
  ADD UNIQUE KEY `uq_phieu_nhap_san_pham` (`ma_phieu_nhap`,`ma_san_pham`),
  ADD KEY `idx_ma_san_pham_nhap` (`ma_san_pham`);

--
-- Chỉ mục cho bảng `chuong_trinh_khuyen_mai`
--
ALTER TABLE `chuong_trinh_khuyen_mai`
  ADD PRIMARY KEY (`ma_chuong_trinh`),
  ADD KEY `fk_khuyen_mai_nguoi_tao` (`ma_nguoi_tao`),
  ADD KEY `idx_khuyen_mai_thoi_gian` (`ngay_bat_dau`,`ngay_ket_thuc`),
  ADD KEY `idx_khuyen_mai_trang_thai` (`trang_thai`);

--
-- Chỉ mục cho bảng `danh_muc`
--
ALTER TABLE `danh_muc`
  ADD PRIMARY KEY (`ma_danh_muc`),
  ADD KEY `fk_danh_muc_cha` (`ma_danh_muc_cha`),
  ADD KEY `idx_ten_danh_muc` (`ten_danh_muc`);

--
-- Chỉ mục cho bảng `don_hang`
--
ALTER TABLE `don_hang`
  ADD PRIMARY KEY (`ma_don_hang`),
  ADD UNIQUE KEY `ma_so_don_hang` (`ma_so_don_hang`),
  ADD KEY `fk_don_hang_nguoi_tao` (`ma_nguoi_tao`),
  ADD KEY `idx_don_hang_ngay_dat` (`ngay_dat`),
  ADD KEY `idx_don_hang_trang_thai` (`trang_thai_don_hang`),
  ADD KEY `idx_don_hang_khach_hang` (`ma_khach_hang`),
  ADD KEY `idx_don_hang_thanh_toan` (`trang_thai_thanh_toan`);

--
-- Chỉ mục cho bảng `gia_tri_thuoc_tinh`
--
ALTER TABLE `gia_tri_thuoc_tinh`
  ADD PRIMARY KEY (`ma_san_pham`,`ma_thuoc_tinh`),
  ADD KEY `fk_gia_tri_thuoc_tinh` (`ma_thuoc_tinh`);

--
-- Chỉ mục cho bảng `gio_hang`
--
ALTER TABLE `gio_hang`
  ADD PRIMARY KEY (`ma_gio_hang`),
  ADD UNIQUE KEY `uq_gio_hang_khach_hang` (`ma_khach_hang`);

--
-- Chỉ mục cho bảng `hinh_anh_san_pham`
--
ALTER TABLE `hinh_anh_san_pham`
  ADD PRIMARY KEY (`ma_hinh_anh`),
  ADD KEY `idx_ma_san_pham` (`ma_san_pham`);

--
-- Chỉ mục cho bảng `khach_hang`
--
ALTER TABLE `khach_hang`
  ADD PRIMARY KEY (`ma_khach_hang`),
  ADD UNIQUE KEY `uq_khach_hang_nguoi_dung` (`ma_nguoi_dung`),
  ADD KEY `idx_ten_khach_hang` (`ten_khach_hang`),
  ADD KEY `idx_sdt_khach_hang` (`so_dien_thoai`);

--
-- Chỉ mục cho bảng `khuyen_mai_don_hang`
--
ALTER TABLE `khuyen_mai_don_hang`
  ADD PRIMARY KEY (`ma_khuyen_mai_don_hang`),
  ADD KEY `idx_kmdh_don_hang` (`ma_don_hang`),
  ADD KEY `idx_kmdh_chuong_trinh` (`ma_chuong_trinh`);

--
-- Chỉ mục cho bảng `lich_su_bao_hanh`
--
ALTER TABLE `lich_su_bao_hanh`
  ADD PRIMARY KEY (`ma_lich_su_bao_hanh`),
  ADD KEY `idx_lshbh_phieu` (`ma_phieu_bao_hanh`),
  ADD KEY `idx_lshbh_nguoi_thuc_hien` (`ma_nguoi_thuc_hien`),
  ADD KEY `idx_lshbh_ngay_thuc_hien` (`ngay_thuc_hien`);

--
-- Chỉ mục cho bảng `lich_su_kho`
--
ALTER TABLE `lich_su_kho`
  ADD PRIMARY KEY (`ma_lich_su_kho`),
  ADD KEY `idx_lich_su_san_pham` (`ma_san_pham`),
  ADD KEY `idx_lich_su_nguoi_dung` (`ma_nguoi_dung`),
  ADD KEY `idx_lich_su_chung_tu` (`loai_chung_tu`,`ma_chung_tu`),
  ADD KEY `idx_lich_su_ngay_tao` (`ngay_tao`);

--
-- Chỉ mục cho bảng `lich_su_trang_thai_don_hang`
--
ALTER TABLE `lich_su_trang_thai_don_hang`
  ADD PRIMARY KEY (`ma_lich_su_trang_thai`),
  ADD KEY `idx_lsttdh_don_hang` (`ma_don_hang`),
  ADD KEY `idx_lsttdh_nguoi_thuc_hien` (`ma_nguoi_thuc_hien`),
  ADD KEY `idx_lsttdh_ngay_thay_doi` (`ngay_thay_doi`),
  ADD KEY `idx_lsttdh_trang_thai_moi` (`trang_thai_moi`);

--
-- Chỉ mục cho bảng `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  ADD PRIMARY KEY (`ma_nguoi_dung`),
  ADD UNIQUE KEY `ten_dang_nhap` (`ten_dang_nhap`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_nguoi_dung_vai_tro` (`ma_vai_tro`);

--
-- Chỉ mục cho bảng `nhat_ky_hoat_dong`
--
ALTER TABLE `nhat_ky_hoat_dong`
  ADD PRIMARY KEY (`ma_nhat_ky`),
  ADD KEY `idx_nkhd_nguoi_dung` (`ma_nguoi_dung`),
  ADD KEY `idx_nkhd_hanh_dong` (`hanh_dong`),
  ADD KEY `idx_nkhd_doi_tuong` (`doi_tuong`),
  ADD KEY `idx_nkhd_ngay_thuc_hien` (`ngay_thuc_hien`);

--
-- Chỉ mục cho bảng `nha_cung_cap`
--
ALTER TABLE `nha_cung_cap`
  ADD PRIMARY KEY (`ma_nha_cung_cap`),
  ADD KEY `idx_ten_nha_cung_cap` (`ten_nha_cung_cap`),
  ADD KEY `idx_sdt_nha_cung_cap` (`so_dien_thoai`);

--
-- Chỉ mục cho bảng `phieu_bao_hanh`
--
ALTER TABLE `phieu_bao_hanh`
  ADD PRIMARY KEY (`ma_phieu_bao_hanh`),
  ADD UNIQUE KEY `ma_so_phieu` (`ma_so_phieu`),
  ADD KEY `fk_pbh_nguoi_tiep_nhan` (`ma_nguoi_tiep_nhan`),
  ADD KEY `idx_pbh_don_hang` (`ma_don_hang`),
  ADD KEY `idx_pbh_khach_hang` (`ma_khach_hang`),
  ADD KEY `idx_pbh_san_pham` (`ma_san_pham`),
  ADD KEY `idx_pbh_trang_thai` (`trang_thai`),
  ADD KEY `idx_pbh_ngay_tiep_nhan` (`ngay_tiep_nhan`);

--
-- Chỉ mục cho bảng `phieu_nhap`
--
ALTER TABLE `phieu_nhap`
  ADD PRIMARY KEY (`ma_phieu_nhap`),
  ADD UNIQUE KEY `ma_so_phieu` (`ma_so_phieu`),
  ADD KEY `fk_phieu_nhap_nha_cung_cap` (`ma_nha_cung_cap`),
  ADD KEY `fk_phieu_nhap_nguoi_dung` (`ma_nguoi_dung`),
  ADD KEY `idx_ngay_nhap` (`ngay_nhap`),
  ADD KEY `idx_trang_thai_phieu_nhap` (`trang_thai`);

--
-- Chỉ mục cho bảng `quyen`
--
ALTER TABLE `quyen`
  ADD PRIMARY KEY (`ma_quyen`),
  ADD UNIQUE KEY `ma_chuc_nang` (`ma_chuc_nang`);

--
-- Chỉ mục cho bảng `san_pham`
--
ALTER TABLE `san_pham`
  ADD PRIMARY KEY (`ma_san_pham`),
  ADD UNIQUE KEY `ma_sku` (`ma_sku`),
  ADD KEY `idx_ten_san_pham` (`ten_san_pham`),
  ADD KEY `idx_ma_danh_muc` (`ma_danh_muc`),
  ADD KEY `idx_ma_thuong_hieu` (`ma_thuong_hieu`);

--
-- Chỉ mục cho bảng `san_pham_khuyen_mai`
--
ALTER TABLE `san_pham_khuyen_mai`
  ADD PRIMARY KEY (`ma_chuong_trinh`,`ma_san_pham`),
  ADD KEY `idx_spkm_san_pham` (`ma_san_pham`);

--
-- Chỉ mục cho bảng `serial_san_pham`
--
ALTER TABLE `serial_san_pham`
  ADD PRIMARY KEY (`ma_serial`),
  ADD UNIQUE KEY `so_serial` (`so_serial`),
  ADD KEY `fk_serial_chi_tiet_phieu_nhap` (`ma_chi_tiet_phieu_nhap`),
  ADD KEY `idx_serial_san_pham` (`ma_san_pham`),
  ADD KEY `idx_serial_trang_thai` (`trang_thai`);

--
-- Chỉ mục cho bảng `thanh_toan`
--
ALTER TABLE `thanh_toan`
  ADD PRIMARY KEY (`ma_thanh_toan`),
  ADD KEY `idx_thanh_toan_don_hang` (`ma_don_hang`),
  ADD KEY `idx_thanh_toan_trang_thai` (`trang_thai`),
  ADD KEY `idx_thanh_toan_phuong_thuc` (`phuong_thuc`),
  ADD KEY `idx_thanh_toan_ma_giao_dich` (`ma_giao_dich`);

--
-- Chỉ mục cho bảng `thuoc_tinh`
--
ALTER TABLE `thuoc_tinh`
  ADD PRIMARY KEY (`ma_thuoc_tinh`),
  ADD UNIQUE KEY `ten_thuoc_tinh` (`ten_thuoc_tinh`);

--
-- Chỉ mục cho bảng `thuong_hieu`
--
ALTER TABLE `thuong_hieu`
  ADD PRIMARY KEY (`ma_thuong_hieu`),
  ADD UNIQUE KEY `ten_thuong_hieu` (`ten_thuong_hieu`);

--
-- Chỉ mục cho bảng `ton_kho`
--
ALTER TABLE `ton_kho`
  ADD PRIMARY KEY (`ma_san_pham`);

--
-- Chỉ mục cho bảng `vai_tro`
--
ALTER TABLE `vai_tro`
  ADD PRIMARY KEY (`ma_vai_tro`),
  ADD UNIQUE KEY `ten_vai_tro` (`ten_vai_tro`);

--
-- Chỉ mục cho bảng `vai_tro_quyen`
--
ALTER TABLE `vai_tro_quyen`
  ADD PRIMARY KEY (`ma_vai_tro`,`ma_quyen`),
  ADD KEY `fk_vai_tro_quyen_quyen` (`ma_quyen`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `chi_tiet_don_hang`
--
ALTER TABLE `chi_tiet_don_hang`
  MODIFY `ma_chi_tiet_don_hang` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `chi_tiet_gio_hang`
--
ALTER TABLE `chi_tiet_gio_hang`
  MODIFY `ma_chi_tiet_gio_hang` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `chi_tiet_phieu_nhap`
--
ALTER TABLE `chi_tiet_phieu_nhap`
  MODIFY `ma_chi_tiet_phieu_nhap` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `chuong_trinh_khuyen_mai`
--
ALTER TABLE `chuong_trinh_khuyen_mai`
  MODIFY `ma_chuong_trinh` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `danh_muc`
--
ALTER TABLE `danh_muc`
  MODIFY `ma_danh_muc` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `don_hang`
--
ALTER TABLE `don_hang`
  MODIFY `ma_don_hang` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `gio_hang`
--
ALTER TABLE `gio_hang`
  MODIFY `ma_gio_hang` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `hinh_anh_san_pham`
--
ALTER TABLE `hinh_anh_san_pham`
  MODIFY `ma_hinh_anh` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `khach_hang`
--
ALTER TABLE `khach_hang`
  MODIFY `ma_khach_hang` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `khuyen_mai_don_hang`
--
ALTER TABLE `khuyen_mai_don_hang`
  MODIFY `ma_khuyen_mai_don_hang` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `lich_su_bao_hanh`
--
ALTER TABLE `lich_su_bao_hanh`
  MODIFY `ma_lich_su_bao_hanh` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `lich_su_kho`
--
ALTER TABLE `lich_su_kho`
  MODIFY `ma_lich_su_kho` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `lich_su_trang_thai_don_hang`
--
ALTER TABLE `lich_su_trang_thai_don_hang`
  MODIFY `ma_lich_su_trang_thai` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  MODIFY `ma_nguoi_dung` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `nhat_ky_hoat_dong`
--
ALTER TABLE `nhat_ky_hoat_dong`
  MODIFY `ma_nhat_ky` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `nha_cung_cap`
--
ALTER TABLE `nha_cung_cap`
  MODIFY `ma_nha_cung_cap` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `phieu_bao_hanh`
--
ALTER TABLE `phieu_bao_hanh`
  MODIFY `ma_phieu_bao_hanh` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `phieu_nhap`
--
ALTER TABLE `phieu_nhap`
  MODIFY `ma_phieu_nhap` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `quyen`
--
ALTER TABLE `quyen`
  MODIFY `ma_quyen` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `san_pham`
--
ALTER TABLE `san_pham`
  MODIFY `ma_san_pham` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `serial_san_pham`
--
ALTER TABLE `serial_san_pham`
  MODIFY `ma_serial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `thanh_toan`
--
ALTER TABLE `thanh_toan`
  MODIFY `ma_thanh_toan` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `thuoc_tinh`
--
ALTER TABLE `thuoc_tinh`
  MODIFY `ma_thuoc_tinh` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `thuong_hieu`
--
ALTER TABLE `thuong_hieu`
  MODIFY `ma_thuong_hieu` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `vai_tro`
--
ALTER TABLE `vai_tro`
  MODIFY `ma_vai_tro` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `chi_tiet_don_hang`
--
ALTER TABLE `chi_tiet_don_hang`
  ADD CONSTRAINT `fk_chi_tiet_don_hang` FOREIGN KEY (`ma_don_hang`) REFERENCES `don_hang` (`ma_don_hang`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chi_tiet_don_hang_san_pham` FOREIGN KEY (`ma_san_pham`) REFERENCES `san_pham` (`ma_san_pham`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `chi_tiet_gio_hang`
--
ALTER TABLE `chi_tiet_gio_hang`
  ADD CONSTRAINT `fk_chi_tiet_gio_hang` FOREIGN KEY (`ma_gio_hang`) REFERENCES `gio_hang` (`ma_gio_hang`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chi_tiet_gio_hang_san_pham` FOREIGN KEY (`ma_san_pham`) REFERENCES `san_pham` (`ma_san_pham`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `chi_tiet_phieu_nhap`
--
ALTER TABLE `chi_tiet_phieu_nhap`
  ADD CONSTRAINT `fk_chi_tiet_phieu_nhap` FOREIGN KEY (`ma_phieu_nhap`) REFERENCES `phieu_nhap` (`ma_phieu_nhap`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chi_tiet_san_pham` FOREIGN KEY (`ma_san_pham`) REFERENCES `san_pham` (`ma_san_pham`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `chuong_trinh_khuyen_mai`
--
ALTER TABLE `chuong_trinh_khuyen_mai`
  ADD CONSTRAINT `fk_khuyen_mai_nguoi_tao` FOREIGN KEY (`ma_nguoi_tao`) REFERENCES `nguoi_dung` (`ma_nguoi_dung`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `danh_muc`
--
ALTER TABLE `danh_muc`
  ADD CONSTRAINT `fk_danh_muc_cha` FOREIGN KEY (`ma_danh_muc_cha`) REFERENCES `danh_muc` (`ma_danh_muc`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `don_hang`
--
ALTER TABLE `don_hang`
  ADD CONSTRAINT `fk_don_hang_khach_hang` FOREIGN KEY (`ma_khach_hang`) REFERENCES `khach_hang` (`ma_khach_hang`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_don_hang_nguoi_tao` FOREIGN KEY (`ma_nguoi_tao`) REFERENCES `nguoi_dung` (`ma_nguoi_dung`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `gia_tri_thuoc_tinh`
--
ALTER TABLE `gia_tri_thuoc_tinh`
  ADD CONSTRAINT `fk_gia_tri_san_pham` FOREIGN KEY (`ma_san_pham`) REFERENCES `san_pham` (`ma_san_pham`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gia_tri_thuoc_tinh` FOREIGN KEY (`ma_thuoc_tinh`) REFERENCES `thuoc_tinh` (`ma_thuoc_tinh`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `gio_hang`
--
ALTER TABLE `gio_hang`
  ADD CONSTRAINT `fk_gio_hang_khach_hang` FOREIGN KEY (`ma_khach_hang`) REFERENCES `khach_hang` (`ma_khach_hang`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `hinh_anh_san_pham`
--
ALTER TABLE `hinh_anh_san_pham`
  ADD CONSTRAINT `fk_hinh_anh_san_pham` FOREIGN KEY (`ma_san_pham`) REFERENCES `san_pham` (`ma_san_pham`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `khach_hang`
--
ALTER TABLE `khach_hang`
  ADD CONSTRAINT `fk_khach_hang_nguoi_dung` FOREIGN KEY (`ma_nguoi_dung`) REFERENCES `nguoi_dung` (`ma_nguoi_dung`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `khuyen_mai_don_hang`
--
ALTER TABLE `khuyen_mai_don_hang`
  ADD CONSTRAINT `fk_kmdh_chuong_trinh` FOREIGN KEY (`ma_chuong_trinh`) REFERENCES `chuong_trinh_khuyen_mai` (`ma_chuong_trinh`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kmdh_don_hang` FOREIGN KEY (`ma_don_hang`) REFERENCES `don_hang` (`ma_don_hang`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `lich_su_bao_hanh`
--
ALTER TABLE `lich_su_bao_hanh`
  ADD CONSTRAINT `fk_lshbh_nguoi_thuc_hien` FOREIGN KEY (`ma_nguoi_thuc_hien`) REFERENCES `nguoi_dung` (`ma_nguoi_dung`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lshbh_phieu_bao_hanh` FOREIGN KEY (`ma_phieu_bao_hanh`) REFERENCES `phieu_bao_hanh` (`ma_phieu_bao_hanh`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `lich_su_kho`
--
ALTER TABLE `lich_su_kho`
  ADD CONSTRAINT `fk_lich_su_kho_nguoi_dung` FOREIGN KEY (`ma_nguoi_dung`) REFERENCES `nguoi_dung` (`ma_nguoi_dung`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lich_su_kho_san_pham` FOREIGN KEY (`ma_san_pham`) REFERENCES `san_pham` (`ma_san_pham`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `lich_su_trang_thai_don_hang`
--
ALTER TABLE `lich_su_trang_thai_don_hang`
  ADD CONSTRAINT `fk_lsttdh_don_hang` FOREIGN KEY (`ma_don_hang`) REFERENCES `don_hang` (`ma_don_hang`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lsttdh_nguoi_thuc_hien` FOREIGN KEY (`ma_nguoi_thuc_hien`) REFERENCES `nguoi_dung` (`ma_nguoi_dung`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  ADD CONSTRAINT `fk_nguoi_dung_vai_tro` FOREIGN KEY (`ma_vai_tro`) REFERENCES `vai_tro` (`ma_vai_tro`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `nhat_ky_hoat_dong`
--
ALTER TABLE `nhat_ky_hoat_dong`
  ADD CONSTRAINT `fk_nkhd_nguoi_dung` FOREIGN KEY (`ma_nguoi_dung`) REFERENCES `nguoi_dung` (`ma_nguoi_dung`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `phieu_bao_hanh`
--
ALTER TABLE `phieu_bao_hanh`
  ADD CONSTRAINT `fk_pbh_don_hang` FOREIGN KEY (`ma_don_hang`) REFERENCES `don_hang` (`ma_don_hang`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pbh_khach_hang` FOREIGN KEY (`ma_khach_hang`) REFERENCES `khach_hang` (`ma_khach_hang`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pbh_nguoi_tiep_nhan` FOREIGN KEY (`ma_nguoi_tiep_nhan`) REFERENCES `nguoi_dung` (`ma_nguoi_dung`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pbh_san_pham` FOREIGN KEY (`ma_san_pham`) REFERENCES `san_pham` (`ma_san_pham`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `phieu_nhap`
--
ALTER TABLE `phieu_nhap`
  ADD CONSTRAINT `fk_phieu_nhap_nguoi_dung` FOREIGN KEY (`ma_nguoi_dung`) REFERENCES `nguoi_dung` (`ma_nguoi_dung`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_phieu_nhap_nha_cung_cap` FOREIGN KEY (`ma_nha_cung_cap`) REFERENCES `nha_cung_cap` (`ma_nha_cung_cap`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `san_pham`
--
ALTER TABLE `san_pham`
  ADD CONSTRAINT `fk_san_pham_danh_muc` FOREIGN KEY (`ma_danh_muc`) REFERENCES `danh_muc` (`ma_danh_muc`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_san_pham_thuong_hieu` FOREIGN KEY (`ma_thuong_hieu`) REFERENCES `thuong_hieu` (`ma_thuong_hieu`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `san_pham_khuyen_mai`
--
ALTER TABLE `san_pham_khuyen_mai`
  ADD CONSTRAINT `fk_spkm_chuong_trinh` FOREIGN KEY (`ma_chuong_trinh`) REFERENCES `chuong_trinh_khuyen_mai` (`ma_chuong_trinh`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_spkm_san_pham` FOREIGN KEY (`ma_san_pham`) REFERENCES `san_pham` (`ma_san_pham`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `serial_san_pham`
--
ALTER TABLE `serial_san_pham`
  ADD CONSTRAINT `fk_serial_chi_tiet_phieu_nhap` FOREIGN KEY (`ma_chi_tiet_phieu_nhap`) REFERENCES `chi_tiet_phieu_nhap` (`ma_chi_tiet_phieu_nhap`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_serial_san_pham` FOREIGN KEY (`ma_san_pham`) REFERENCES `san_pham` (`ma_san_pham`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `thanh_toan`
--
ALTER TABLE `thanh_toan`
  ADD CONSTRAINT `fk_thanh_toan_don_hang` FOREIGN KEY (`ma_don_hang`) REFERENCES `don_hang` (`ma_don_hang`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `ton_kho`
--
ALTER TABLE `ton_kho`
  ADD CONSTRAINT `fk_ton_kho_san_pham` FOREIGN KEY (`ma_san_pham`) REFERENCES `san_pham` (`ma_san_pham`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `vai_tro_quyen`
--
ALTER TABLE `vai_tro_quyen`
  ADD CONSTRAINT `fk_vai_tro_quyen_quyen` FOREIGN KEY (`ma_quyen`) REFERENCES `quyen` (`ma_quyen`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vai_tro_quyen_vai_tro` FOREIGN KEY (`ma_vai_tro`) REFERENCES `vai_tro` (`ma_vai_tro`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
