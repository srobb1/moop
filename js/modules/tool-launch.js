/**
 * Open a Toolbox tool on the current organism selection.
 *
 * The toolbox (lib/tool_section.php) renders, on pages where the user picks organisms, buttons
 * that call handleToolClick(toolId). Home, group and multi-organism pages each defined that
 * function as a near-identical copy; this is the one implementation they all call.
 *
 * POSTs the organisms to the tool in a new tab -- the live selection, so unticked organisms are
 * excluded, and a POST so a long list never hits a URL length limit.
 *
 * Only organisms[] is sent, never the page's group: the receiving tools give a group priority
 * over organisms[] (parseContextParameters), so sending it would undo the user's deselections.
 *
 * Expects the global sitePath ('/moop').
 *
 * @param {string}   toolId    the tool's id (button id is "tool-btn-{toolId}")
 * @param {string[]} organisms the selected organism names
 */
function postSelectionToTool(toolId, organisms) {
    if (!organisms || organisms.length === 0) {
        alert('Please select at least one organism');
        return;
    }
    const btn = document.getElementById(`tool-btn-${toolId}`);
    const toolPath = btn ? btn.getAttribute('data-tool-path') : null;
    if (!toolPath) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `${sitePath}${toolPath}`;
    form.target = '_blank';
    organisms.forEach(org => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'organisms[]';
        input.value = org;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
