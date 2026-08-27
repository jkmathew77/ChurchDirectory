# Sparkill Location Release

**Move effective date:** August 23, 2026  
**Production deployment date:** August 27, 2026  
**Site Core version:** `0.3.0`  
**Site Core data version:** `003`  
**Exact deployed source commit:** `a2ef358c9a2dca5eff4d0d7648c8197d7859f178`  
**Controlled deployment workflow run:** `33105770172`

## New worship location

St. Thekla Malankara Orthodox Church now worships at:

```text
Sacred Heart Chapel
175 Route 340
Sparkill, NY 10976
```

The public map destination is the Sparkill address above.

## Recurring Sunday schedule

| Time | Service or activity |
| --- | --- |
| 8:00 AM | Lilyo |
| 8:30 AM | Morning Prayer |
| 9:00 AM | Holy Qurbana |
| 10:10 AM | Dismissal |
| 10:30 AM | Refreshments / Fellowship |
| 10:45 AM | Tree of Life |
| 11:30 AM | End of Tree of Life |

## Public-site changes

- The homepage now presents the move announcement, the Sacred Heart Chapel exterior image, the new address, the full Sunday schedule, directions and a link to parking information.
- The Contact Us page uses centralized Site Core location data, the recurring schedule and the existing WPForms contact form.
- A published `/visit-us/` page contains the full parking and arrival map, arrival instructions, address and Sunday schedule.
- `Visit Us` was added to the primary navigation without replacing existing menu items.
- A Site Core announcement titled `St. Thekla Has a New Home` was published with the church exterior image.
- The old Nyack and West Nyack location references were removed from current published pages. Historical event records were not blindly rewritten.
- The public Site Core API now exposes the Sparkill contact details, move date, visit-page URL, approved image URLs and recurring schedule.

## Media

The following church-provided images were optimized and imported into the WordPress Media Library rather than committed to the product repository:

- Sacred Heart Chapel exterior / move announcement image
- Sacred Heart Chapel parking and arrival map

Both attachments have descriptive alternative text. The originals remain outside the product source tree.

## Safety and validation

- A complete database and site-files backup was created and checksum-verified before deployment.
- The previous Site Core plugin directory and original page/option state were retained privately for rollback.
- Site Core 0.3.0 was deployed from the exact merged commit after PHP syntax validation.
- The data migration updated only the recognized prior Nyack address and the exact recognized six-row schedule; unexpected customized values would have stopped the release.
- Community Directory tables, users and member records were not modified.
- Aggregate Community Directory table counts were compared before and after the release.
- Cache-busted production checks covered the homepage, Contact Us, Visit Us, Community Directory login, directory session endpoint, Site Core public API and weekly-schedule API.
- A second check ran from a GitHub-hosted external runner after the Bluehost cache purge.

## Rollback records

The full backup, prior plugin directory and serialized page/option snapshot are retained privately under the Bluehost account. They are intentionally excluded from GitHub because they may contain configuration or site content that must not be published.
