<?php

declare(strict_types=1);

namespace App\Domain\Logging\Enums;

/**
 * Katalog tindakan yang tercatat di audit_logs (bagian 6 dokumen rancangan).
 *
 * Berbeda dari SecurityEventType: yang di sini BOLEH memuat nominal, karena
 * jejak ini milik workspace dan tidak pernah bisa dibaca admin platform.
 */
enum AuditAction: string
{
    case TransactionCreated = 'transaction.created';
    case TransactionPosted = 'transaction.posted';
    case TransactionUpdated = 'transaction.updated';
    case TransactionVoided = 'transaction.voided';
    case TransactionReversed = 'transaction.reversed';

    case AccountCreated = 'account.created';
    case AccountUpdated = 'account.updated';
    case AccountArchived = 'account.archived';

    case CategoryCreated = 'category.created';
    case CategoryUpdated = 'category.updated';
    case CategoryArchived = 'category.archived';

    case BudgetCreated = 'budget.created';
    case BudgetUpdated = 'budget.updated';

    case PeriodLocked = 'period.locked';
    case PeriodReopened = 'period.reopened';

    case ReconciliationPerformed = 'reconciliation.performed';
    case AdjustmentCreated = 'adjustment.created';

    case DataImported = 'data.imported';
    case DataExported = 'data.exported';

    case AttachmentUploaded = 'attachment.uploaded';
    case AttachmentDownloaded = 'attachment.downloaded';
    case AttachmentDeleted = 'attachment.deleted';

    case InvoiceCreated = 'invoice.created';
    case InvoicePaid = 'invoice.paid';
    case InvoiceVoided = 'invoice.voided';

    public function label(): string
    {
        return match ($this) {
            self::TransactionCreated => 'Transaksi dibuat',
            self::TransactionPosted => 'Transaksi dicatat',
            self::TransactionUpdated => 'Transaksi diubah',
            self::TransactionVoided => 'Transaksi dibatalkan',
            self::TransactionReversed => 'Transaksi dibalik',
            self::AccountCreated => 'Akun dibuat',
            self::AccountUpdated => 'Akun diubah',
            self::AccountArchived => 'Akun ditutup',
            self::CategoryCreated => 'Kategori dibuat',
            self::CategoryUpdated => 'Kategori diubah',
            self::CategoryArchived => 'Kategori diarsipkan',
            self::BudgetCreated => 'Anggaran dibuat',
            self::BudgetUpdated => 'Anggaran diubah',
            self::PeriodLocked => 'Periode dikunci',
            self::PeriodReopened => 'Periode dibuka kembali',
            self::ReconciliationPerformed => 'Cash opname dilakukan',
            self::AdjustmentCreated => 'Penyesuaian dibuat',
            self::DataImported => 'Data diimpor',
            self::DataExported => 'Data diekspor',
            self::AttachmentUploaded => 'Lampiran diunggah',
            self::AttachmentDownloaded => 'Lampiran diunduh',
            self::AttachmentDeleted => 'Lampiran dihapus',
            self::InvoiceCreated => 'Tagihan dibuat',
            self::InvoicePaid => 'Tagihan dibayar',
            self::InvoiceVoided => 'Tagihan dibatalkan',
        };
    }
}
