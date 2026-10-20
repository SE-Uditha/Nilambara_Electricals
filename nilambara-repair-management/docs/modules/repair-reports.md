# Repair reports

`/reports/repair` supports all recorded job statuses, received dates, customer, technician, search, sorting, pagination, print and authorized exports. Pending inspection, under repair, waiting for parts, completed, ready, delivered, cancelled and unable-to-repair reports are status filters on this report. There is one row per job; status history is not joined in a way that multiplies rows.

`/reports/technicians` groups current assignments, completed/pending counts, status breakdown and recorded completion time. Reassigned work is attributed to the currently assigned technician. This is an explicit attribution rule, not a historical workload or performance rating. Completion time is elapsed received-to-completed time; missing completion dates remain empty.

Receptionists and technicians have repair reporting grants. Technician queries enforce current assignment even when another employee ID is supplied. Broader financial reports remain restricted. See the Step 11 summary for verification and remaining accounting assumptions.
