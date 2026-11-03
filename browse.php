<?php
/**
 * MOOP's own home -- search, organisms, tools -- at an address that ALWAYS shows it, whatever the
 * site chose for /<site>/ (Admin -> Manage Pages: a page above it, or a page instead). Link it from
 * the menu as the built-in "Search & browse" (Admin -> Manage Menu). On a site that kept MOOP's
 * home, this is simply a second address for the same page.
 */
define('MOOP_BUILTIN_HOME', true);
require __DIR__ . '/index.php';
