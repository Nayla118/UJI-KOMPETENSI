<?php

/**
 * Role -> permission map for the Travelo web portal.
 *
 * Roles answer WHAT a user may do; the destination_user pivot answers WHERE
 * they may do it. The two checks are always combined for non-admin users.
 *
 * `*` is reserved for the global admin. Route middleware uses the `permission:`
 * alias and controllers/Gate checks use the same names, so a permission is
 * never enforced only in the menu.
 */
return [

    'roles' => [

        'admin' => ['*'],

        'owner' => [
            'portal.access',
            'tickets.redeem',
            'scanner.history',
            'destinations.view',
            'destinations.create',
            'destinations.update',
            'destinations.delete',
            'packages.view',
            'packages.create',
            'packages.update',
            'packages.delete',
            'availability.manage',
            'promos.manage',
            'bookings.view',
            'change_requests.decide',
            'refunds.manage',
            'payments.view',
            'reports.revenue',
            'reports.checkin',
            'visitor_documents.view',
            'staff.manage',
            'activity.view',
        ],

        'manager' => [
            'portal.access',
            'tickets.redeem',
            'scanner.history',
            'destinations.view',
            'destinations.update',
            'packages.view',
            'packages.update',
            'availability.manage',
            'bookings.view',
            'change_requests.decide',
            'refunds.manage',
            'payments.view',
            'reports.revenue',
            'reports.checkin',
            'visitor_documents.view',
            // Deliberately absent: destinations.create/delete,
            // packages.create/delete, promos.manage, staff.manage,
            // activity.view, admin.manage and review moderation.
        ],

        'gate_staff' => [
            'portal.access',
            'tickets.redeem',
            'scanner.history',
            // Deliberately absent: every management permission and every
            // sensitive financial/visitor-document permission.
        ],

        'traveller' => [
            // No web portal access. The Android API has its own auth.
        ],
    ],

    'labels' => [
        'portal.access' => 'Portal access',
        'tickets.redeem' => 'Scan/redeem tickets',
        'scanner.history' => 'Own scan history',
        'destinations.view' => 'View destinations',
        'destinations.create' => 'Create destinations',
        'destinations.update' => 'Edit destinations',
        'destinations.delete' => 'Delete destinations',
        'packages.view' => 'View tour packages',
        'packages.create' => 'Create tour packages',
        'packages.update' => 'Edit tour packages',
        'packages.delete' => 'Delete tour packages',
        'availability.manage' => 'Manage quota and closed dates',
        'promos.manage' => 'Manage promo codes',
        'bookings.view' => 'View bookings',
        'change_requests.decide' => 'Approve/reject cancellations',
        'refunds.manage' => 'Manage refunds',
        'payments.view' => 'View payments',
        'reports.revenue' => 'Revenue reports',
        'reports.checkin' => 'Check-in reports',
        'visitor_documents.view' => 'View visitor identity documents',
        'staff.manage' => 'Manage destination staff',
        'activity.view' => 'View activity log',
        'admin.manage' => 'Global admin management',
        'admin.owners.manage' => 'Manage owner accounts',
        'tickets.reissue' => 'Reissue tickets',
    ],
];
