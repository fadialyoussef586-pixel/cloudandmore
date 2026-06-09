<?php

$invoiceNumber = $invoiceNumber ?? '';
$wrapperClass = $wrapperClass ?? 'invoice-print-footer';
echo renderInvoiceProfessionalFooter($invoiceNumber, $wrapperClass);
