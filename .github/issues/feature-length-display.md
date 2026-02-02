---
name: Display feature length on parent page
about: Show sequence length (bp/aa) for features
title: 'Display feature length on parent page'
labels: ['enhancement', 'parent-page', 'configuration']
assignees: []
---

## Feature Request

Display feature sequence length on the parent page.

## Context

The parent page doesn't currently display feature sequence length. Users want to know the size of features they're viewing.

## Requirements

- Should show the number of base pairs (bp) for nucleotide features
- Should show the number of amino acids (aa) for protein features
- Add configuration option in Manage Organism to enable/disable length display
- Format clearly with unit abbreviation

## Example Display

```
Feature: insulin gene
Sequence: ATGATGATG... (3,214 bp)

Protein: insulin
Sequence: MVLWAALL... (110 aa)
```

## Configuration

- Add option to `organism.json` to control whether length is displayed
- Or global setting in `site_config.php`
