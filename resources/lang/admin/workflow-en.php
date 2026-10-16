<?php

declare(strict_types=1);

return ['admin_workflow' => [
    'complete' => 'This case is complete.',
    'cancelled' => 'This case is cancelled. Reopen it only if work should continue.',
    'ready' => 'Arrange collection or delivery, confirm access to the final documents, then mark the case completed.',
    'translation_active' => 'Translation is in progress. Review and upload the finished translation, then continue any other selected services or prepare the file for collection or delivery.',
    'embassy_active' => 'Embassy work is in progress. Update the reference and dates, then upload the returned document.',
    'mofa_active' => 'MOFA is in progress. Update the reference and dates, or upload the document when it returns.',
    'start_embassy' => 'MOFA is complete. Review the returned document and begin the selected embassy service when the file is ready.',
    'remaining_mofa' => 'Embassy work is complete. Review the remaining selected MOFA service and update its processing record.',
    'confirm_translation' => 'The recorded processing is complete. Confirm the selected translation is finished and upload the final files before arranging collection or delivery.',
    'release_files' => 'The recorded processing is complete. Review and release the final files, then arrange collection or delivery.',
    'outstanding_invoices' => 'Review the outstanding invoices, send them if needed, and record the remaining payment.',
    'paid_translation' => 'Payment is recorded. Begin the selected translation, then review and upload the finished file.',
    'paid_processing' => 'Payment is recorded. Begin the selected services and update their processing records as the file moves.',
    'paid_other' => 'Payment is recorded. Review the selected services, carry out the work, and upload the final documents when ready.',
    'prepare_quote' => 'Review the request, confirm the services, and prepare a quotation.',
    'draft_quote' => 'A draft quotation is ready. Check the total, then send it to the customer.',
    'sent_quote' => 'The quotation has been sent. Wait for the customer, or follow up if it is close to expiry.',
    'revise_quote' => 'Review the rejected or expired quotation with the customer and prepare a revision if work should continue.',
    'generate_invoice' => 'The quotation is accepted. Generate its invoice and arrange payment.',
    'update_actual_stage' => 'Update the status when the next real step happens. Internal notes stay hidden from the customer.',
    'progress_label' => 'Case progress for selected services',
    'workspace_label' => 'Case sections',
    'reason_label' => 'Status note or correction reason',
    'correction_help' => 'Choose the actual stage for the selected services. Moving backwards or changing a closed case requires a reason and the confirmation below.',
    'confirm_correction' => 'I intend to move this case backwards or change a closed case, and have recorded the reason.',
    'advance_process' => 'Advance the case status when appropriate',
    'active_status_required' => 'Choose an active case status.',
    'reversal_required' => 'Moving backwards or changing a closed case requires a reason and explicit confirmation.',
]];
