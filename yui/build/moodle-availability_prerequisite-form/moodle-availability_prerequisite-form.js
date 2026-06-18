YUI.add('moodle-availability_prerequisite-form', function (Y, NAME) {

/**
 * JavaScript for the "Course completion" availability condition editor.
 *
 * Follows Moodle coding standards:
 *  - No inline styles — all styling via styles.css and Bootstrap/Moodle classes.
 *  - No emoji — SVG icon for the search field.
 *  - Per-node event listeners, no shared addedEvents flags.
 *  - Fully accessible: keyboard navigation (↑↓ Enter Esc), ARIA attributes.
 *
 * @module moodle-availability_prerequisite-form
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
M.availability_prerequisite = M.availability_prerequisite || {};
M.availability_prerequisite.form = Y.Object(M.core_availability.plugin);
M.availability_prerequisite.form.courses = [];

M.availability_prerequisite.form.initInner = function(courses, capped) {
    this.capped = capped || false;
    this.courses = courses;
};

M.availability_prerequisite.form.getNode = function(json) {

    var self         = this;
    var selectedId   = (json && json.course) ? parseInt(json.course, 10) : 0;
    var selectedName = '';
    var focusedIndex = -1;

    if (selectedId) {
        for (var k = 0; k < self.courses.length; k++) {
            if (self.courses[k].id === selectedId) {
                selectedName = self.courses[k].name;
                break;
            }
        }
    }

    // ── Lang strings ────────────────────────────────────────────────────────
    var strSearch     = M.util.get_string('searchcourse',     'availability_prerequisite');
    var strLabelCrs   = M.util.get_string('label_course',     'availability_prerequisite');
    var strLabelComp  = M.util.get_string('label_completion',  'availability_prerequisite');
    var strNoResults  = M.util.get_string('noresults',         'availability_prerequisite');
    var strVisit      = M.util.get_string('visit_course',      'availability_prerequisite');
    var strComplete   = M.util.get_string('option_complete',   'availability_prerequisite');
    var strIncomplete = M.util.get_string('option_incomplete', 'availability_prerequisite');
    var strError      = M.util.get_string('error_selectcourse','availability_prerequisite');

    // SVG magnifier icon (no emoji, no font icon dependency — works everywhere).
    var svgIcon =
        '<svg class="acc-search-icon" xmlns="http://www.w3.org/2000/svg" ' +
             'viewBox="0 0 20 20" fill="none" stroke="currentColor" ' +
             'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' +
             'aria-hidden="true" focusable="false">' +
            '<circle cx="8.5" cy="8.5" r="5.5"/>' +
            '<line x1="13" y1="13" x2="18" y2="18"/>' +
        '</svg>';

    // ── HTML structure ───────────────────────────────────────────────────────
    var html =
        '<span class="availability_prerequisite acc-widget">' +

            // Hidden field — stores the actual selected course ID.
            '<input type="hidden" name="course" value="' + (selectedId || 0) + '" />' +

            // Course picker section.
            '<span class="acc-section">' +
                '<label class="acc-label">' + strLabelCrs + '</label>' +
                '<span class="acc-search-wrap">' +
                    svgIcon +
                    '<input type="text" name="coursesearch" ' +
                           'class="form-control acc-search-input" ' +
                           'value="' + selectedName.replace(/"/g, '&quot;') + '" ' +
                           'placeholder="' + strSearch + '" ' +
                           'autocomplete="off" ' +
                           'role="combobox" ' +
                           'aria-expanded="true" ' +
                           'aria-autocomplete="list" />' +
                '</span>' +
                '<span name="courselist" class="acc-results" role="listbox"></span>' +
            '</span>' +

            // Completion state section.
            '<span class="acc-section">' +
                '<label class="acc-label">' + strLabelComp + '</label>' +
                '<select name="e" class="custom-select form-control acc-completion-select">' +
                    '<option value="1">' + strComplete   + '</option>' +
                    '<option value="0">' + strIncomplete + '</option>' +
                '</select>' +
            '</span>' +

        '</span>';

    var node = Y.Node.create(html);

    // Restore saved e value.
    if (json && json.e !== undefined) {
        node.one('select[name=e]').set('value', '' + json.e);
    }

    // ── renderList: rebuild the results list ─────────────────────────────────
    function renderList(query) {
        var list    = node.one('[name=courselist]');
        var trimmed = (query || '').trim().toLowerCase();
        var html    = '';
        var shown   = 0;
        var total   = 0;

        for (var i = 0; i < self.courses.length; i++) {
            var c = self.courses[i];
            if (trimmed && c.name.toLowerCase().indexOf(trimmed) === -1) {
                continue;
            }
            total++;
            if (shown >= 50) {
                continue;
            }
            shown++;

            var isSelected = (c.id === selectedId);

            // Highlight matched substring using a CSS class, not inline style.
            var display = c.name;
            if (trimmed) {
                var idx = display.toLowerCase().indexOf(trimmed);
                if (idx >= 0) {
                    display =
                        display.substring(0, idx) +
                        '<strong class="acc-highlight">' +
                            display.substring(idx, idx + trimmed.length) +
                        '</strong>' +
                        display.substring(idx + trimmed.length);
                }
            }

            var rowClasses = 'acc-row' +
                (isSelected ? ' acc-selected' : '');

            html +=
                '<span class="' + rowClasses + '" ' +
                      'data-id="'   + c.id + '" ' +
                      'data-name="' + c.name.replace(/"/g, '&quot;') + '" ' +
                      'data-url="'  + c.url + '" ' +
                      'role="option" ' +
                      'aria-selected="' + (isSelected ? 'true' : 'false') + '">' +

                    '<span class="acc-row-name">' +
                        (isSelected ? '<span class="acc-check" aria-hidden="true">\u2713</span>' : '') +
                        display +
                    '</span>' +

                    '<a href="' + c.url + '" target="_blank" rel="noopener noreferrer" ' +
                       'class="acc-visit" ' +
                       'title="' + strVisit + ': ' + c.name.replace(/"/g, '&quot;') + '">' +
                        strVisit +
                    '</a>' +

                '</span>';
        }

        if (shown === 0) {
            html = '<span class="acc-message">' + strNoResults + '</span>';
        }

        // When the server capped the course list, tell the user to refine.
        if (self.capped) {
            html += '<span class="acc-overflow">' +
                        M.util.get_string('toomany', 'availability_prerequisite') +
                    '</span>';
        }

        list.setHTML(html);
        focusedIndex = -1;
    }

    // Initial render.
    renderList('');

    // ── Live search ──────────────────────────────────────────────────────────
    var searchInput = node.one('input[name=coursesearch]');

    // keyup catches held-down keys and deletion; input catches paste/cut.
    searchInput.on('keyup', function(e) {
        // Don't re-render for navigation keys — handled in keydown.
        if (e.keyCode === 13 || e.keyCode === 38 || e.keyCode === 40 || e.keyCode === 27) {
            return;
        }
        renderList(searchInput.get('value'));
    });

    searchInput.on('input', function() {
        renderList(searchInput.get('value'));
    });

    // ── Row selection ────────────────────────────────────────────────────────
    node.one('[name=courselist]').delegate('click', function(e) {
        // Ignore clicks on the "Open course" link itself.
        if (e.target.get('tagName').toLowerCase() === 'a' || e.target.ancestor('a')) {
            return;
        }
        var row    = e.currentTarget;
        selectedId = parseInt(row.getData('id'), 10);
        searchInput.set('value', row.getData('name'));
        node.one('input[name=course]').set('value', selectedId);
        renderList(searchInput.get('value'));
        M.core_availability.form.update();
    }, '.acc-row');

    // ── Keyboard navigation ──────────────────────────────────────────────────
    searchInput.on('keydown', function(e) {
        var rows  = node.one('[name=courselist]').all('.acc-row');
        var count = rows.size();

        function applyFocus(idx) {
            rows.each(function(r, i) {
                r.removeClass('acc-focused');
                if (i === idx) {
                    r.addClass('acc-focused');
                    r.scrollIntoView(false);
                }
            });
        }

        if (e.keyCode === 40) { // Arrow Down
            e.preventDefault();
            focusedIndex = Math.min(focusedIndex + 1, count - 1);
            applyFocus(focusedIndex);

        } else if (e.keyCode === 38) { // Arrow Up
            e.preventDefault();
            focusedIndex = Math.max(focusedIndex - 1, 0);
            applyFocus(focusedIndex);

        } else if (e.keyCode === 13) { // Enter — select focused or first row
            e.preventDefault();
            var idx    = (focusedIndex >= 0) ? focusedIndex : 0;
            var picked = rows.item(idx);
            if (picked) {
                selectedId = parseInt(picked.getData('id'), 10);
                searchInput.set('value', picked.getData('name'));
                node.one('input[name=course]').set('value', selectedId);
                renderList('');
                M.core_availability.form.update();
            }

        } else if (e.keyCode === 27) { // Escape — restore last confirmed name
            var currentId = parseInt(node.one('input[name=course]').get('value'), 10);
            if (currentId) {
                for (var k = 0; k < self.courses.length; k++) {
                    if (self.courses[k].id === currentId) {
                        searchInput.set('value', self.courses[k].name);
                        break;
                    }
                }
            }
            renderList('');
        }
    });

    // ── Completion state change ──────────────────────────────────────────────
    node.one('select[name=e]').on('change', function() {
        M.core_availability.form.update();
    });

    return node;
};

M.availability_prerequisite.form.fillValue = function(value, node) {
    value.course = parseInt(node.one('input[name=course]').get('value'), 10);
    value.e      = parseInt(node.one('select[name=e]').get('value'), 10);
};

M.availability_prerequisite.form.fillErrors = function(errors, node) {
    var courseid = parseInt(node.one('input[name=course]').get('value'), 10);
    if (!courseid || courseid === 0) {
        errors.push('availability_prerequisite:error_selectcourse');
    }
};

}, '@VERSION@', {"requires": ["base", "node", "event", "moodle-core_availability-form"]});
