<?php
const ORDER_STATUSES = [
    'PENDING' => 'Chờ duyệt', 'CONFIRMED' => 'Đã duyệt',
    'PROCESSING' => 'Đang xử lý', 'SHIPPING' => 'Đang giao hàng',
    'COMPLETED' => 'Hoàn thành', 'CANCELLED' => 'Đã hủy',
];
const PAYMENT_STATUSES = [
    'UNPAID' => 'Chưa thanh toán', 'PARTIAL' => 'Thanh toán một phần',
    'PAID' => 'Đã thanh toán', 'REFUNDED' => 'Đã hoàn tiền',
];

// Mỗi trạng thái chỉ được chuyển sang đúng bước kế tiếp.
const ORDER_NEXT_STATUSES = [
    'CONFIRMED' => 'PROCESSING',
    'PROCESSING' => 'SHIPPING',
    'SHIPPING' => 'COMPLETED',
];

function orderEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function orderMoney($value): string
{
    return number_format((float)$value, 0, ',', '.') . ' ₫';
}

function orderDate($value): string
{
    $timestamp = $value ? strtotime((string)$value) : false;
    return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
}

function orderStatusBadge($value, bool $payment = false): string
{
    $labels = $payment ? PAYMENT_STATUSES : ORDER_STATUSES;
    $colors = [
        'PENDING' => 'warning text-dark', 'CONFIRMED' => 'primary',
        'PROCESSING' => 'info text-dark', 'SHIPPING' => 'primary',
        'COMPLETED' => 'success', 'CANCELLED' => 'secondary',
        'UNPAID' => 'secondary', 'PARTIAL' => 'warning text-dark',
        'PAID' => 'success', 'REFUNDED' => 'dark',
    ];
    return '<span class="badge bg-' . ($colors[$value] ?? 'secondary') . '">'
        . orderEscape($labels[$value] ?? $value) . '</span>';
}
