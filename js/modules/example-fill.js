/**
 * Example chips under a search box (moop_example_chips_html(), lib/home_examples.php): a click puts
 * the chip's text into the box it names (data-example-fill = a CSS selector) and, with
 * data-example-submit, sends that box's form. One handler for every page that has them.
 */
document.addEventListener('click', function (e) {
    var chip = e.target.closest('[data-example-fill]');
    if (!chip) return;
    var box = document.querySelector(chip.getAttribute('data-example-fill'));
    if (!box) return;
    box.value = chip.getAttribute('data-value') || chip.textContent.trim();
    box.dispatchEvent(new Event('input', { bubbles: true }));
    if (chip.hasAttribute('data-example-submit') && box.form) {
        if (box.form.requestSubmit) box.form.requestSubmit(); else box.form.submit();
    } else {
        box.focus();
    }
});
