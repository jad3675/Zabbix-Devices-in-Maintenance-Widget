# Devices in Maintenance (Zabbix dashboard widget)

A dashboard widget listing every host Zabbix currently has suppressed,
whoever placed the window.

Target: **Zabbix 7.0+** (the widget framework introduced there). Standalone —
it does not need, and does not talk to, the Maintenance Windows module.

---

## Why this exists

Zabbix will happily **include** hosts in maintenance and **exclude** them, but
it will not **isolate** them. Host navigator's "Show hosts in maintenance" and
the Monitoring → Hosts filter of the same name are both additive toggles. There
is no "only what is suppressed" view anywhere in the product, and there is
certainly not one on a dashboard.

The usual workaround is a `zabbix[host,,maintenance]` internal item on every
template, which is a lot of item churn, a lot of history, and a lot of template
editing for a fact Zabbix already tracks in the `hosts` table. This widget reads
it from there.

## Install

```bash
cd /usr/share/zabbix/ui/modules      # 7.2+
# cd /usr/share/zabbix/modules       # 7.0/7.1
cp -r /path/to/maintdevices .
```

Administration → General → Modules → **Scan directory**, enable *Devices in
maintenance*. It then appears in the widget type list when editing a dashboard.

**There is no configuration file.** Nothing to chown, nothing to protect,
nothing to keep in sync across customers. See below for why that is not an
accident.

## No service token, by design

The query is `host.get` filtered on `maintenance_status`, run as the logged-in
user. Three consequences worth stating plainly:

- **Permission-scoped for free.** Nobody sees a suppressed host they could not
  already see in Monitoring → Hosts. Safe to put on a customer-facing dashboard.
- **Catches windows nothing has a record of.** Starting from hosts rather than
  from `maintenance.get` means it sees maintenance scheduled by hand in Data
  collection, and hosts swept in by a host-group target that nobody listed
  individually. Those are exactly the ones you want to find.
- **No elevated call on a refresh loop.** A wallboard left open for a month
  refreshes this every 60 seconds. An Admin-level API call on that cadence, per
  viewer, per dashboard, is an audit-log problem waiting to happen.

The cost of that choice: no window **name** and no **end time**, since
`maintenance.get` is Admin-only. In exchange you get *duration* ("for 3d 4h"),
computed from `maintenance_from`, which is the more useful number anyway. If
you need the window name and the end time, that is what the Maintenance
Windows module's **Devices in Maintenance** page is for.

## Configuration

| Field | Notes |
|---|---|
| Host groups | Scope to a customer or a site. |
| Hosts | Narrow further, if you want a fixed watchlist. |
| Host tags | And/Or, full operator set. Pairs well with a `site:` tag scheme. |
| Show | Count / List / Count and list. |
| Collection | Any, or only hosts whose window is still polling, or only the ones gone dark. |
| Flag after (days) | Highlight anything suppressed longer than this. 0 turns it off. Default 7. |
| Show only flagged | Turns the widget into a pure exception tile. |
| Sort by | Host name, or longest-suppressed first. |
| Show host tags | Off saves a `selectTags` on the query and a column. |

## The number that matters

**Flag after (days)** is the reason to put this on a wallboard rather than
bookmark a page.

A host suppressed for 40 days is not in maintenance. It is a hole in your
coverage that somebody opened and forgot about, and it is the mechanism behind
the worst conversation an MSP has: *"why didn't you alert on that outage?"*
*"because it has been in maintenance since March."*

In count mode the flagged figure renders as an amber badge next to the total.
Amber rather than red on purpose: a long window is a question, not an outage.

A tile with **Show only flagged** on, **Flag after** at 30, and no group filter
is the one to put where people will see it. Most days it reads zero.

## Known limits

- **Count is capped at 2000 hosts** per widget. A dashboard tile is not a
  reporting tool, and an unbounded `host.get` on a large estate is not
  something to run every 60 seconds.
- **No window name or end time.** Deliberate; see above.
- **No broadcasting.** 7.0 widgets can broadcast `_hostids` so that clicking a
  row drives other widgets on the dashboard. Not wired up here. It is the
  obvious v2 and needs a JS class plus an `out` section in the manifest.
- **Host names link to Latest data.** Not the full host context menu, which
  would be nicer but is a more version-sensitive API to lean on.
- **Dashboards only help if somebody is looking.** The real answer to long
  forgotten maintenance is a trigger, not a tile: one script item polling the
  API for maintenance state, on the Zabbix self-monitoring host, with a trigger
  on "any host suppressed > 30d". That finds you at 3am. This widget does not.

## Files

```
maintdevices/
├── manifest.json               type: widget, action widget.maintdevices.view
├── Widget.php                  primary class, display-mode constants
├── includes/
│   └── WidgetForm.php          field definitions and validation
├── actions/
│   └── WidgetView.php          host.get, filtering, duration, staleness
├── views/
│   ├── widget.view.php         what renders in the tile
│   └── widget.edit.php         what renders in the config dialog
└── assets/
    └── css/widget.css
```

Field names must line up in three places: defined in `WidgetForm`, rendered in
`widget.edit.php`, read in `WidgetView`. Miss the second and the field is
invisible in the config dialog while silently keeping its default forever;
miss the third and it does nothing at all. The test suite checks all three
agree.
