# Email from the site — what should send one (2026-11-23)

Status: **list agreed in principle, not built.** This host cannot send mail yet (no
sendmail/postfix, no SMTP settings in MOOP). Sofia wants email set up after the visitor
features (zero-result searches, how people arrived, outside 404s, feedback link).

Rule for every item: **email only what needs a person to act, at most once per hour per kind**,
and the email says what happened, how bad, and the one page to open. Everything else stays on
the dashboard / System Monitor. A digest is better than a stream.

## Send at once (something is broken or under attack)
| # | Trigger | Where it is detected today | Why email |
|---|---|---|---|
| 1 | A **real person** (passed the human check, signed in, or institute network) hit a server error (5xx) | request log, `visitors.errors` (System Monitor red box) | A user just failed; scraper errors never mail |
| 2 | **Housekeeping run died** or has not run for > 3 hours | `housekeeping_health()` | Every other check depends on it |
| 3 | A **site is down**: the home page or a gene page does not answer 200 from this host | new tiny check (System Monitor collector, runs as root every minute) | Nobody notices at night |
| 4 | **php-fpm hit max_children** / out-of-memory kill | collector (`fpm_max_children_reached`), journal | Visitors are waiting or failing |
| 5 | **Disk** under 10% free on the data drive or /var | collector | Logs, caches and BLAST stop silently |
| 6 | **Permission / SELinux** finding of HIGH impact (e.g. a credential file world-readable) | `permission_check` | Security |
| 7 | **HTTPS certificate** expires within 14 days | new check (setup-check already reads it) | Site goes dark for everyone |
| 8 | **Rate-limit settings out of step** with MOOP (collector sync state = error) | System Monitor sync card | Internal users could be blocked |
| 9 | A **burst** from one address on an expensive endpoint (agent API busiest minute ≥ 60, or heavy-group refusals ≥ 100/hour from one address) | request log / nginx 429s | Something is looping or scraping |

## Daily digest (one email, only if something is in it)
| # | Item |
|---|---|
| 10 | Pending **OS security updates** (security_updates report), watched packages first |
| 11 | **Library vulnerabilities** found (library_advisories) |
| 12 | **JBrowse tracks / search indexes** out of step or failed to rebuild (jbrowse_indexes) |
| 13 | **Searches that found nothing** — count, and the words (once recorded; admin-only) |
| 14 | **Feedback / data requests** sent from the site's feedback link |
| 15 | **Outside links that 404** — new ones since yesterday, with where they came from |

## Weekly summary (Monday morning)
| # | Item |
|---|---|
| 16 | People this week vs last, countries, top organisms, new organisms used for the first time |
| 17 | Who got in: % turned away, search engines, agent API calls |
| 18 | Data nobody used (30 days) — only changes since last week |
| 19 | Account requests / new users (if account requests are added) |

## Account mail (later, separate)
| # | Item |
|---|---|
| 20 | Password reset link (today: `scripts/reset_password.php` by an admin) |
| 21 | A collaborator's access changed / account created |

## What building it needs
1. IT: the Stowers **SMTP relay** host/port, and whether it needs auth; a sender address
   (e.g. `simrbase-noreply@stowers.org`).
2. `lib/mail.php` — one function, plain-text + simple HTML, sent over SMTP (PHP's socket, or a
   small Composer package), never `mail()` (no local MTA). SELinux: `httpd_can_sendmail` /
   `httpd_can_network_connect` to the relay — check with setup-check.
3. Site Configuration: **alert address(es)**, which kinds to send, a "Send test email" button.
4. A **mail queue + throttle** file in the cache (one per kind per hour), sent from housekeeping's
   background run and the collector — never from a visitor's page request.
5. setup-check + INSTALL.md + README + CLAUDE.md entries (keep the install workflow current).
