# Sacred Heart Chapel Location Move

**Effective date:** August 23, 2026  
**Site Core release:** 0.3.0

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

## Public content scope

The production migration is expected to:

- place the approved new-home image and live address/schedule content on the homepage;
- create or update `/visit-us/` with the parking map and arrival guidance;
- update the Contact Us page while preserving its WPForms form;
- add a Visit Us primary-navigation item;
- update current public widgets or options that contain the known Nyack or West Nyack location;
- preserve historically accurate event locations and revisions;
- leave Community Directory member records and authentication data unchanged.

## Media accessibility

- New-home image alt text: `Exterior of Sacred Heart Chapel, the new worship location of St. Thekla Malankara Orthodox Church in Sparkill, New York.`
- Parking map alt text: `Aerial parking map for Sacred Heart Chapel showing entrances, designated parking, no-parking areas, chapel entrance and exit, and St. Martin Hall restrooms.`

The images are stored in the WordPress Media Library rather than packaged in the plugin repository.

## Migration guardrails

Site Core data version `003` updates the saved location and weekly schedule only when production matches the approved prior Nyack baseline, is empty, or is already on the approved Sparkill values. An unexpected saved value pauses the migration rather than overwriting it.
