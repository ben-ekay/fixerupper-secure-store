<?php

/*
|--------------------------------------------------------------------------
| APPLICATION SETTINGS
|--------------------------------------------------------------------------
|
| BASE_URL is the folder the site is served from (no trailing slash).
| Every link, form action and redirect is built from it via url(),
| so the site can be moved by changing this one value.
|
| This file holds no secrets, so it is safe to commit.
|
*/

const BASE_URL = '/fixerupper-summative';

// All dates shown to users are UK time (handles GMT/BST automatically).
date_default_timezone_set('Europe/London');

/*
|--------------------------------------------------------------------------
| SESSION TIMEOUTS (seconds)
|--------------------------------------------------------------------------
|
| A signed-in user is logged out after 30 minutes without activity,
| or 8 hours after logging in, whichever comes first.
| The guest cart is kept; only the login is removed.
|
*/

const SESSION_IDLE_TIMEOUT = 30 * 60;
const SESSION_ABSOLUTE_TIMEOUT = 8 * 60 * 60;

/*
|--------------------------------------------------------------------------
| STOREFRONT DEPARTMENTS
|--------------------------------------------------------------------------
|
| The category slugs shown in the shop, in display order. Products in any
| other category (such as the original demonstration items) are kept in the
| database for past orders and the admin area, but are not listed for sale.
|
*/

const STOREFRONT_DEPARTMENTS = [
    'power-tools',
    'hand-tools',
    'electrical',
    'saws-cutting',
    'home-improvement',
    'tool-storage',
];
