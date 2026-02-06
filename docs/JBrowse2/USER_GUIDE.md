# JBrowse2 User Guide

**Audience:** End Users (Researchers, Collaborators)  
**Purpose:** How to use JBrowse2 to explore genomes

---

## Getting Started

### Accessing JBrowse2

1. Log in to MOOP (if you want access to restricted assemblies)
2. Navigate to **JBrowse2** in the main menu
3. You'll see a list of genome assemblies you can access

### Understanding Your Access Level

Your access level determines which assemblies you can view:

| Access Level | What You See |
|-------------|--------------|
| **Guest** (not logged in) | Public assemblies only |
| **Collaborator** (logged in) | Public + Collaborator assemblies |
| **Administrator** | All assemblies |

Your current access level is displayed at the top of the page.

---

## Browsing Assemblies

### Assembly List

The main page shows all assemblies you can access:

```
┌─────────────────────────────────────────────────┐
│ Anoura caudifer (GCA_004027475.1)              │
│ Aliases: ACA1, GCA_004027475.1                 │
│ [Public]                          [View Genome]│
└─────────────────────────────────────────────────┘
```

Each assembly card shows:
- **Display Name** - Common name and assembly ID
- **Aliases** - Alternative names for this assembly
- **Access Badge** - Public, Collaborator, or Admin
- **View Button** - Opens the genome browser

### Opening an Assembly

1. Click **"View Genome →"** on any assembly
2. JBrowse2 will load in an embedded viewer
3. Wait a few seconds for the genome to load

---

## Using JBrowse2

### Basic Navigation

#### Pan (Move Left/Right)
- **Click and drag** the main view
- **Arrow keys** (← →) on keyboard
- **Scroll horizontally** with trackpad/mouse

#### Zoom In/Out
- **Mouse wheel** up/down
- **Pinch gesture** on trackpad
- **+/- buttons** in toolbar

#### Jump to Location
1. Click the **location box** (top of viewer)
2. Type a location:
   - `chr1:1000-2000` (chromosome range)
   - `GENE123` (gene name, if indexed)
   - `scaffold_45:5000` (scaffold location)
3. Press **Enter**

### Viewing Tracks

#### Reference Sequence Track
Shows the actual DNA sequence (A, T, G, C) when zoomed in enough.

#### Annotation Tracks
Shows genes, exons, transcripts, and other features.

- **Colored boxes** = Features (genes, exons, etc.)
- **Arrows** indicate strand direction (→ forward, ← reverse)
- **Click feature** to see details

#### Quantitative Tracks (BigWig)
Shows coverage data (RNA-seq, ChIP-seq, etc.)

- **Height** = signal strength
- **Color** indicates value range
- **Hover** to see exact values

#### Alignment Tracks (BAM)
Shows read alignments from sequencing.

- **Gray bars** = individual reads
- **Colored bars** = mismatches/variants
- **Click read** to see alignment details

### Track Controls

Each track has a menu (click **⋮** or right-click):

- **Show/Hide** - Toggle track visibility
- **Track Settings** - Adjust height, colors, filters
- **About Track** - View metadata and description
- **Delete Track** - Remove from view (temporary)

---

## Common Tasks

### Finding a Gene

1. **By Name** (if gene names are indexed):
   - Type gene name in location box: `BRCA1`
   - Press Enter

2. **By Browsing**:
   - Navigate to the chromosome
   - Zoom in to see gene annotations
   - Click gene features to see names

### Comparing Regions

1. Open first region of interest
2. Note the location (e.g., `chr1:1000-5000`)
3. Use **File → Open New View** (if available)
4. Navigate to second region
5. View side-by-side

### Exporting Data

#### Export Image
1. Right-click on viewer
2. Select **"Save as SVG"** or **"Save as PNG"**
3. Choose location to save

#### Export Track Data
1. Click track menu (⋮)
2. Select **"Export track data"**
3. Choose format (BED, GFF, etc.)
4. Save file

### Adjusting Track Display

#### Change Track Height
1. Click track menu (⋮)
2. Drag height slider
3. Or enter exact pixel height

#### Change Track Colors
1. Click track menu (⋮)
2. Select **"Track settings"**
3. Adjust color scheme
4. Click **"Apply"**

#### Filter Features
1. Click annotation track menu (⋮)
2. Select **"Filter features"**
3. Set filters (e.g., "gene_type = protein_coding")
4. Click **"Apply"**

---

## Understanding Features

### Gene Features

When you click a gene, you'll see:

```
Gene: BRCA1
Type: protein_coding
Location: chr17:43,044,295-43,125,483
Strand: + (forward)
Length: 81,189 bp
Exons: 24
Transcript: ENST00000357654
```

### Feature Colors

Different feature types have different colors:
- **Blue** - Genes (default)
- **Green** - Exons
- **Red** - UTRs (untranslated regions)
- **Yellow** - Non-coding RNAs
- **Gray** - Other features

Colors may vary by track configuration.

---

## Tips & Tricks

### Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `←` `→` | Pan left/right |
| `+` `-` | Zoom in/out |
| `Ctrl+F` | Open search |
| `Ctrl+O` | Open location dialog |
| `Esc` | Close dialogs |

### Performance Tips

1. **Close unused tracks** - Improves loading speed
2. **Zoom out gradually** - Large regions take time to render
3. **Use smaller window** - Renders faster than full width
4. **Refresh page** if stuck - Sometimes helps reset state

### Saving Your Session

JBrowse2 uses browser storage to remember:
- Last viewed location
- Open tracks
- Track settings

To start fresh:
1. Clear browser cache
2. Or use **"File → New Session"**

---

## Troubleshooting

### Assembly Not Loading

**Symptoms:** Spinner keeps spinning, no genome appears

**Solutions:**
1. Refresh the page (F5)
2. Clear browser cache (Ctrl+Shift+Delete)
3. Try a different browser
4. Check your internet connection
5. Contact admin if problem persists

### Tracks Not Showing

**Symptoms:** Reference sequence loads but no tracks appear

**Possible Causes:**
1. No tracks configured for this assembly yet
2. Tracks require higher access level than yours
3. Track files are being moved to remote server

**Solutions:**
1. Check with administrator about available tracks
2. Log in if you're viewing as guest
3. Try refreshing after 5 minutes

### Slow Performance

**Symptoms:** Panning/zooming is sluggish

**Solutions:**
1. Close unused tracks (fewer tracks = faster)
2. Zoom out less (very large regions are slow)
3. Use a faster computer/device
4. Close other browser tabs
5. Try Chrome or Firefox (usually fastest)

### "Token Expired" Error

**Symptoms:** Tracks disappear after ~1 hour of viewing

**Cause:** Security tokens expire after 1 hour

**Solution:**
1. Refresh the page (F5)
2. Select assembly again
3. New tokens will be generated

---

## Getting Help

### Need More Assemblies?

Contact your administrator to request access to additional genome assemblies.

### Found a Bug?

Report issues to the MOOP administrator with:
1. What you were trying to do
2. What happened instead
3. Your browser and OS version
4. Screenshot if possible

### Want to Learn More?

- [JBrowse2 Official Documentation](https://jbrowse.org/jb2/)
- [JBrowse2 Tutorials](https://jbrowse.org/jb2/docs/tutorials/)
- [JBrowse2 FAQ](https://jbrowse.org/jb2/docs/faq/)

---

## FAQ

**Q: Why can't I see all assemblies?**  
A: Access is controlled by your account permissions. Contact an admin for more access.

**Q: Can I download entire genomes?**  
A: Contact the administrator for bulk data access. JBrowse2 is designed for browsing, not bulk downloads.

**Q: Why are there no tracks on my genome?**  
A: Tracks may not be configured yet, or they may be in the process of being moved to the tracks server.

**Q: Can I upload my own tracks?**  
A: Not directly. Contact an administrator to have your tracks added to the system.

**Q: How often is data updated?**  
A: Assembly data is typically static. Track data (like RNA-seq) may be updated periodically. Check with administrator.

**Q: Can I share a specific view with a colleague?**  
A: Yes! Copy the URL from your browser - it includes the current location. Your colleague will need appropriate access to view the assembly.

---

**Need additional help?** Contact your MOOP administrator.
