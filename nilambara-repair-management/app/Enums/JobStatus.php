<?php

namespace App\Enums;

enum JobStatus: string
{
    case Received = 'Received';
    case PendingInspection = 'Pending Inspection';
    case UnderInspection = 'Under Inspection';
    case QuotationPending = 'Quotation Pending';
    case WaitingForCustomerApproval = 'Waiting for Customer Approval';
    case CustomerContactFailed = 'Customer Contact Failed';
    case AdvancePaymentPending = 'Advance Payment Pending';
    case WaitingForSpareParts = 'Waiting for Spare Parts';
    case UnderRepair = 'Under Repair';
    case PriceModification = 'Price Modification';
    case ReAssembling = 'Re – Assembling';
    case RepairCompleted = 'Repair Completed';
    case ReadyForCollection = 'Ready for Collection';
    case CustomerContactFailedAfterReady = 'Customer Contact Failed After Ready';
    case Delivered = 'Delivered';
    case Cancelled = 'Cancelled';
    case UnableToRepair = 'Unable to Repair';

    public function label(): string
    {
        return $this->value;
    }
}
