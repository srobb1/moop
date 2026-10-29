<?php
/**
 * Guide for Manage Glossary (admin/pages/manage_glossary.php). If the page changes, change this.
 * A term shows only where code calls gloss('term') (lib/glossary.php) — there is no
 * automatic linking of words in text.
 */
echo help_modal('glossary-guide', 'How the Glossary works', [
    ['heading' => 'What this page is for', 'cards' => [
        ['label' => 'Definitions behind the dotted words', 'text' => 'Terms with a dashed underline across the site show a short definition when hovered or tapped. Those definitions are written here, once.'],
        ['label' => 'Edit once, changes everywhere', 'text' => 'Editing a definition updates every page that shows that term, immediately and with no code change.'],
        ['label' => 'Adding a term', 'text' => 'A new term appears only where a page already asks for it by name. Adding one here does not underline that word in text by itself; that needs a small code change on the page.'],
        ['label' => 'Keep it short', 'text' => 'A definition is read in a small pop-up mid-sentence: one or two plain sentences.'],
    ]],
]);
