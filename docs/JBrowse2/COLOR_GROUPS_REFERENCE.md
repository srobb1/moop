# Color Groups Reference

**Complete reference for all available color groups in JBrowse2 track generation**

---

## Quick Reference

```bash
# List all color groups
python3 tools/jbrowse/generate_tracks_from_sheet.py --list-colors

# Get suggestions for N files
python3 tools/jbrowse/generate_tracks_from_sheet.py --suggest-colors 12
```

---

## All Color Groups (28 total)

### Large Groups (15+ colors)

| Group | Colors | Type | Best For |
|-------|--------|------|----------|
| **rainbow** | 20 | qualitative | Maximum variety, many distinct samples |
| **browns** | 17 | sequential | Large groups, earthy themes, natural samples |
| **greens** | 16 | sequential | Large groups, plant data, growth studies |
| **earth** | 16 | sequential | Natural/soil samples, ecological data |
| **pastels** | 16 | qualitative | Subtle differences, gentle visualization |
| **vibrant** | 16 | qualitative | Presentations, posters, high visibility |

### Medium Groups (10-14 colors)

| Group | Colors | Type | Best For |
|-------|--------|------|----------|
| **purples** | 14 | sequential | Larger groups, time series, epigenetics |
| **warm** | 12 | sequential | Upregulated genes, active states, heat |
| **cool** | 12 | sequential | Downregulated genes, inactive states, cold |
| **neon** | 12 | qualitative | High contrast, dark backgrounds |
| **grayscale** | 12 | sequential | Black & white publications |
| **blues** | 11 | sequential | Samples, replicates, standard use |
| **yellows** | 11 | sequential | Expression data, intensity |
| **diffs** | 11 | qualitative | Distinct samples (original default) |
| **sea** | 10 | sequential | Marine organisms, ocean data |
| **sunset** | 10 | sequential | Time progression, day/night |

### Small Groups (6-9 colors)

| Group | Colors | Type | Best For |
|-------|--------|------|----------|
| **cyans** | 9 | sequential | Water/aquatic samples |
| **reds** | 9 | sequential | Treatments, stress, danger |
| **grays** | 9 | sequential | Controls, baselines, neutral |
| **monoblues** | 9 | sequential | Intensity gradients (single hue) |
| **monoreds** | 9 | sequential | Severity, danger levels |
| **galaxy** | 9 | sequential | Space themes, dark backgrounds |
| **monogreens** | 8 | sequential | Growth, abundance gradients |
| **monopurples** | 8 | sequential | Epigenetic marks, modifications |
| **forest** | 8 | sequential | Vegetation, forest data |
| **contrast** | 8 | qualitative | Accessibility, maximum distinction |
| **pinks** | 6 | sequential | Small groups, feminine themes |
| **oranges** | 5 | sequential | Very small groups, highlights |

---

## Color Types

### Sequential
Colors progress from light to dark (or dark to light). Best for:
- Intensity/expression data
- Time series
- Gradients
- Ordered samples

**Examples:** blues, reds, greens, monoblues

### Qualitative  
Distinct, unrelated colors. Best for:
- Different samples/conditions
- Unordered categories
- Maximum visual distinction
- Comparative studies

**Examples:** rainbow, diffs, vibrant, pastels, contrast

---

## Usage in Google Sheets

### Basic Usage

```
# Combo Track Name
## blues: Sample Group
label	key	...
sample1	Sample 1	...
sample2	Sample 2	...
### end
```

### Special Syntax

```
## exact=OrangeRed: Group Name     # Use specific color
## blues3: Group Name              # Use 4th color from blues (0-indexed)
## rainbow: Diverse Samples        # Use rainbow for variety
```

---

## Choosing the Right Group

### By Number of Files

| Files | Recommended Groups |
|-------|-------------------|
| 2-5 | `oranges`, `pinks`, `contrast` |
| 6-9 | `reds`, `cyans`, `grays`, `monoblues` |
| 10-14 | `blues`, `yellows`, `purples`, `warm`, `cool` |
| 15-17 | `greens`, `earth`, `pastels`, `vibrant`, `browns` |
| 18-20 | `rainbow` |
| 20+ | Combine multiple groups or use exact colors |

### By Data Type

| Data Type | Suggested Groups |
|-----------|-----------------|
| **RNA-seq** | blues, greens, purples |
| **ChIP-seq** | monopurples, purples |
| **ATAC-seq** | warm, vibrant |
| **DNA methylation** | monoblues, cool |
| **Time series** | sunset, warm→cool, grayscale |
| **Treatment vs Control** | reds vs blues, warm vs cool |
| **Tissues/Organs** | rainbow, vibrant, earth |
| **Replicates** | monoblues, monogreens, grayscale |
| **Environmental** | earth, forest, sea |
| **Cell types** | pastels, vibrant, diffs |

### By Context

| Context | Suggested Groups |
|---------|-----------------|
| **Publications** | blues, grays, grayscale |
| **Presentations** | vibrant, neon, rainbow |
| **Posters** | vibrant, warm, contrast |
| **Web display** | any except neon |
| **Colorblind-safe** | contrast, grayscale, earth |
| **Dark backgrounds** | neon, galaxy, vibrant |

---

## Examples

### Example 1: Strand-Specific RNA-seq (4 files)

```
# Wild Type vs Treatment
## blues: Wild Type
wt_pos	WT (+)	...
wt_neg	WT (-)	...
## reds: Treatment
treat_pos	Treatment (+)	...
treat_neg	Treatment (-)	...
### end
```

Colors: Navy, Blue (blues) + DarkRed, Red (reds)

### Example 2: Time Series (10 files)

```
# Developmental Time Course
## sunset: Timepoints
t0	0 hours	...
t2	2 hours	...
t4	4 hours	...
...
t18	18 hours	...
### end
```

Colors: Dark purple → Red → Orange → Yellow (sunset gradient)

### Example 3: Multiple Tissues (16 files)

```
# Tissue Expression Atlas
## rainbow: Tissues
brain	Brain	...
heart	Heart	...
liver	Liver	...
...
### end
```

Colors: 16 maximally distinct colors

### Example 4: Replicates (8 files)

```
# Biological Replicates
## monoblues: Sample A Replicates
a1	A Rep 1	...
a2	A Rep 2	...
a3	A Rep 3	...
a4	A Rep 4	...
## monoreds: Sample B Replicates
b1	B Rep 1	...
b2	B Rep 2	...
b3	B Rep 3	...
b4	B Rep 4	...
### end
```

Colors: Light blue → Dark blue + Light red → Dark red

---

## Error Handling

### Too Few Colors

When your group has too few colors:

```
⚠ COLOR GROUP TOO SMALL
Group 'oranges' only has 5 colors
but you need at least 8 colors for this track group.

✓ SUGGESTED COLOR GROUPS:
Group           Colors   Type         Best For
------------------------------------------------------------
contrast        8        qualitative  accessibility
forest          8        sequential   vegetation data
monogreens      8        sequential   growth/abundance
...

RECOMMENDED:
  ## contrast: Your Group Name
```

### Solution Options

1. **Use suggested group**
   ```
   ## contrast: Your Group Name
   ```

2. **Split into multiple groups**
   ```
   ## oranges: First 5
   file1	...
   ...
   file5	...
   ## reds: Next 5
   file6	...
   ...
   file10	...
   ```

3. **Use exact colors**
   ```
   ## exact=Orange: File 1
   ## exact=OrangeRed: File 2
   ## exact=Coral: File 3
   ```

---

## Color Visualization

### Blues (11 colors)
Navy → Blue → RoyalBlue → SteelBlue → DodgerBlue → DeepSkyBlue → CornflowerBlue → SkyBlue → LightSkyBlue → LightSteelBlue → LightBlue

### Reds (9 colors)
DarkRed → Red → Firebrick → Crimson → IndianRed → LightCoral → Salmon → DarkSalmon → LightSalmon

### Greens (16 colors)
DarkGreen → DarkOliveGreen → ForestGreen → SeaGreen → Olive → OliveDrab → MediumSeaGreen → LimeGreen → Lime → MediumSpringGreen → DarkSeaGreen → MediumAquamarine → YellowGreen → LawnGreen → LightGreen → GreenYellow

### Rainbow (20 colors)
Maximum variety of distinct colors using hex codes

### Warm (12 colors)
Dark red → Red → Orange → Yellow → Yellow-green (warm gradient)

### Cool (12 colors)
Dark blue → Blue → Cyan → Teal → Green (cool gradient)

---

## Tips

### 1. Match Colors to Biological Meaning

- **Red**: upregulation, stress, heat, danger
- **Blue**: downregulation, cold, water, calm
- **Green**: growth, plants, positive
- **Purple**: epigenetic, modifications
- **Orange**: intermediate, transitional

### 2. Use Consistent Schemes

Within a project, use consistent color schemes:
- Always use `blues` for wild type
- Always use `reds` for treatment
- Always use `grays` for controls

### 3. Consider Your Audience

- **Color vision deficiency**: Use `contrast`, `grayscale`, or limit red-green combinations
- **Publications**: Use `grayscale` for B&W journals
- **Presentations**: Use `vibrant` or `neon` for visibility

### 4. Preview Before Committing

Use `--dry-run` to preview without generating tracks:
```bash
python3 generate_tracks_from_sheet.py "SHEET_ID" \
    --organism Org --assembly Asm --dry-run
```

---

## Advanced: Custom Colors

### Define Your Own Colors

Edit `generate_tracks_from_sheet.py`:

```python
COLORS = {
    # ... existing colors ...
    'mycolors': ['#FF0000', '#00FF00', '#0000FF', '#FFFF00'],
}

COLOR_GROUP_INFO = {
    # ... existing info ...
    'mycolors': {
        'count': 4,
        'best_for': 'my special use case',
        'type': 'qualitative'
    },
}
```

### Use Hex Colors Directly

```
## exact=#FF5733: Custom Color
```

---

## Summary

✅ **28 color groups** - From 5 to 20 colors each  
✅ **Smart suggestions** - Get recommendations for your data  
✅ **Clear error messages** - Know when you need more colors  
✅ **Multiple types** - Sequential and qualitative palettes  
✅ **Easy commands** - List and suggest colors anytime  

**Commands:**
```bash
# List all groups
python3 tools/jbrowse/generate_tracks_from_sheet.py --list-colors

# Get suggestions for N files
python3 tools/jbrowse/generate_tracks_from_sheet.py --suggest-colors 12
```
