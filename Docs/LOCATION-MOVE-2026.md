# Sacred Heart Chapel Location Move

**Status:** Completed and externally verified  
**Move effective date:** August 23, 2026  
**Production deployment completed:** August 28, 2026 at 02:22 UTC  
**Site Core release:** 0.3.0  
**Site Core data version:** 003  
**Exact deployed source:** `a2ef358c9a2dca5eff4d0d7648c8197d7859f178`

## Approved public location

St. Thekla Malankara Orthodox Church now worships at:

- Sacred Heart Chapel
- 175 Route 340
- Sparkill, NY 10976

Map destination:

`https://www.google.com/maps/search/?api=1&query=175+Route+340+Sparkill+NY+10976`

## Approved recurring Sunday schedule

| Time | Service or activity |
| --- | --- |
| 8:00 AM | Lilyo |
| 8:30 AM | Morning Prayer |
| 9:00 AM | Holy Qurbana |
| 10:10 AM | Dismissal |
| 10:30 AM | Refreshments / Fellowship |
| 10:45 AM | Tree of Life |
| 11:30 AM | End of Tree of Life |

## Completed public content changes

- The homepage displays the approved new-home image, address, directions and seven-row Sunday schedule.
- `/visit-us/` is published with the parking map and arrival guidance.
- The Contact Us page uses the centralized Sparkill location and schedule while preserving WPForms.
- The primary navigation includes Visit Us.
- The move announcement **St. Thekla Has a New Home** is published with the exterior image.
- Current published pages contain none of the known West Nyack or Nyack location values.
- The Site Core public API exposes the Sparkill address, move date and both image payloads.
- Community Directory member records, authentication data and aggregate custom-table counts were unchanged.

## Media accessibility

- New-home image attachment: `4452`
- Parking-map attachment: `4453`
- New-home image alt text: `Exterior of Sacred Heart Chapel, the new worship location of St. Thekla Malankara Orthodox Church in Sparkill, New York.`
- Parking map alt text: `Aerial parking map for Sacred Heart Chapel showing entrances, designated parking, no-parking areas, chapel entrance and exit, and St. Martin Hall restrooms.`

The images are stored in the WordPress Media Library rather than packaged in the product plugin.

## Validation record

- Fresh full backup and checksum verification completed before the release.
- Production deployment run: `33135656681`
- Independent external smoke-test run: `33135788238`
- Homepage, Contact Us, Visit Us, donation, Community Directory login, session endpoint, public API and schedule API checks passed.
- The externally observed schedule exactly matched all seven approved rows.
- Rollback copies of the prior plugin and changed WordPress content/options were retained privately.

## Migration guardrails

Site Core data migration `003` changed the saved location and schedule only because the production values matched the approved prior baseline. The deployment would have stopped rather than overwrite an unexpected saved value.

The remaining manual confirmation is an end-to-end Contact Us submission and verification that the message arrives in the church inbox.
