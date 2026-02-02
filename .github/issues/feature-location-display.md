---
name: Add feature location display to parent page
about: Display chromosome/contig coordinates on parent page (requires GFF integration)
title: 'Add feature location display to parent page (requires GFF file integration)'
labels: ['enhancement', 'parent-page']
assignees: []
---

## Feature Request

Display feature location (chromosome/contig and coordinates) on the parent page.

## Context

Currently the parent page does not show where a feature is located in the genome (e.g., Chr1:1000-2000).

## Requirements

- Feature location data is available in GFF files
- This feature should be added once GFF file actions are incorporated into the system
- Display format should be clear: e.g., "Chr1:1000-2000" or "contig_5:5432-6789"

## Related

- Depends on: GFF file integration for coordinate data
- Affects: Parent page display

## Example

```
Feature: insulin
Location: Chr11:2,192,001-2,292,991 (forward strand)
```
