<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Shared Font Awesome icon picker for format_iccaformat.
 *
 * One icon list and one behaviour, used by the Card Styling Rules page and by the
 * per-card "Custom icon" override on the section and activity edit forms.
 *
 * The behaviour is loaded once per page through $PAGE->requires->js_init_code(), so it
 * runs in the page footer — inline <script> placed inside a moodleform cannot be
 * relied on, as Moodle rebuilds parts of the form after load. Each field wires itself
 * up through inline event attributes, so it works however late it is added to the DOM.
 *
 * @package   format_iccaformat
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_iccaformat\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Icon picker helper.
 */
class icon_picker {

    /** @var bool Whether the picker script has been queued on this page. */
    private static $jsqueued = false;

    /**
     * The icons offered in the picker.
     *
     * Classic names (e.g. fa-book) render as "fa fa-book"; newer solid-style names are
     * stored with their style prefix (e.g. "fa-solid fa-layer-group").
     *
     * @return string[]
     */
    public static function icons(): array {
        return [
        'fa-home', 'fa-user', 'fa-users', 'fa-group',
        'fa-cog', 'fa-cogs', 'fa-wrench', 'fa-search',
        'fa-bell', 'fa-star', 'fa-heart', 'fa-flag',
        'fa-bookmark', 'fa-tag', 'fa-tags', 'fa-lock',
        'fa-unlock', 'fa-key', 'fa-shield', 'fa-globe',
        'fa-map', 'fa-map-marker', 'fa-compass', 'fa-clock-o',
        'fa-file', 'fa-file-o', 'fa-file-text', 'fa-file-text-o',
        'fa-file-pdf-o', 'fa-file-word-o', 'fa-file-excel-o', 'fa-file-powerpoint-o',
        'fa-file-image-o', 'fa-file-archive-o', 'fa-file-code-o', 'fa-files-o',
        'fa-copy', 'fa-clone', 'fa-folder', 'fa-folder-o',
        'fa-folder-open', 'fa-folder-open-o', 'fa-book', 'fa-graduation-cap',
        'fa-mortar-board', 'fa-university', 'fa-pencil', 'fa-pencil-square-o',
        'fa-edit', 'fa-lightbulb-o', 'fa-flask', 'fa-envelope',
        'fa-envelope-o', 'fa-comment', 'fa-comment-o', 'fa-comments',
        'fa-comments-o', 'fa-phone', 'fa-video-camera', 'fa-microphone',
        'fa-bullhorn', 'fa-rss', 'fa-share', 'fa-share-alt',
        'fa-play', 'fa-play-circle', 'fa-play-circle-o', 'fa-pause',
        'fa-stop', 'fa-film', 'fa-music', 'fa-headphones',
        'fa-image', 'fa-camera', 'fa-check', 'fa-check-circle',
        'fa-check-circle-o', 'fa-check-square', 'fa-check-square-o', 'fa-times',
        'fa-times-circle', 'fa-circle', 'fa-circle-o', 'fa-tasks',
        'fa-list', 'fa-list-ul', 'fa-list-ol', 'fa-th',
        'fa-th-large', 'fa-table', 'fa-bars', 'fa-bar-chart',
        'fa-line-chart', 'fa-pie-chart', 'fa-database', 'fa-server',
        'fa-cloud', 'fa-cloud-upload', 'fa-cloud-download', 'fa-download',
        'fa-upload', 'fa-external-link', 'fa-link', 'fa-refresh',
        'fa-repeat', 'fa-undo', 'fa-arrow-right', 'fa-arrow-left',
        'fa-plus', 'fa-plus-circle', 'fa-minus', 'fa-minus-circle',
        'fa-question', 'fa-question-circle', 'fa-question-circle-o', 'fa-info',
        'fa-info-circle', 'fa-exclamation-triangle', 'fa-warning', 'fa-trophy',
        'fa-certificate', 'fa-thumbs-up', 'fa-thumbs-down', 'fa-hand-pointer-o',
        'fa-cube', 'fa-cubes', 'fa-puzzle-piece', 'fa-archive',
        'fa-briefcase', 'fa-calendar', 'fa-calendar-o', 'fa-print',
        'fa-wpforms', 'fa-address-card', 'fa-clipboard', 'fa-solid fa-layer-group',
        'fa-solid fa-chalkboard-user', 'fa-solid fa-graduation-cap', 'fa-solid fa-book-open', 'fa-solid fa-clipboard-list',
        'fa-solid fa-file-lines', 'fa-solid fa-circle-check', 'fa-solid fa-lightbulb', 'fa-solid fa-brain',
        'fa-solid fa-microscope', 'fa-solid fa-flask', 'fa-solid fa-scale-balanced', 'fa-solid fa-gavel',
        'fa-solid fa-landmark', 'fa-solid fa-building-columns', 'fa-solid fa-scroll', 'fa-solid fa-feather',
        'fa-solid fa-pen-nib', 'fa-solid fa-highlighter', 'fa-solid fa-folder-open', 'fa-solid fa-boxes-stacked',
        ];
    }

    /**
     * Full CSS class string for a stored icon value.
     *
     * @param  string $icon Stored value, e.g. "fa-book" or "fa-solid fa-lightbulb".
     * @return string
     */
    public static function css_class(string $icon): string {
        $icon = trim($icon);
        if ($icon === '') {
            return '';
        }
        if (strpos($icon, 'fa-solid') === 0 || strpos($icon, 'fa ') === 0) {
            return $icon;
        }
        return 'fa ' . $icon;
    }

    /**
     * HTML for one picker field: live preview, search box, and the dropdown.
     *
     * The chosen value is copied into a separate field that the form actually submits,
     * identified by element id ($syncid) or by name ($syncname).
     *
     * @param  string $value     Current icon value.
     * @param  string $syncid    Id of the field to keep in step ('' if using $syncname).
     * @param  string $syncname  Name of the field to keep in step ('' if using $syncid).
     * @param  array  $ids       Optional element ids: 'input', 'preview', 'dropdown'.
     * @return string
     */
    public static function field_html(string $value, string $syncid = '', string $syncname = '', array $ids = []): string {
        self::require_js();

        $idattr = function (string $key) use ($ids): string {
            return !empty($ids[$key]) ? ' id="' . s($ids[$key]) . '"' : '';
        };

        $html  = '<div class="icca-iconpicker" style="position:relative;">';
        $html .= '<div style="display:flex;align-items:center;gap:6px;">';
        $html .= '<span class="icca-iconpicker-preview"' . $idattr('previewwrap')
            . ' style="width:34px;height:34px;border:1px solid #dee2e6;border-radius:6px;display:flex;'
            . 'align-items:center;justify-content:center;flex-shrink:0;color:#6c757d;background:#f8f9fa;">';
        $html .= '<i' . $idattr('preview') . ' class="' . s(self::css_class($value)) . '" aria-hidden="true"></i>';
        $html .= '</span>';
        $html .= '<input type="text"' . $idattr('input') . ' value="' . s($value) . '"'
            . ' placeholder="' . s(get_string('labelrules_ui_searchicons', 'format_iccaformat')) . '"'
            . ' autocomplete="off"'
            . ($syncid !== '' ? ' data-sync-id="' . s($syncid) . '"' : '')
            . ($syncname !== '' ? ' data-sync-name="' . s($syncname) . '"' : '')
            . ' oninput="window.iccaIconPicker && iccaIconPicker.input(this)"'
            . ' onfocus="window.iccaIconPicker && iccaIconPicker.open(this)"'
            . ' onkeydown="if (event.key === \'Escape\' && window.iccaIconPicker) { iccaIconPicker.close(this); }"'
            . ' style="font-family:monospace;font-size:1rem;flex:1;min-width:0;border:1px solid #dee2e6;'
            . 'border-radius:6px;padding:7px 10px;">';
        $html .= '</div>';
        $html .= '<div class="icca-iconpicker-dd"' . $idattr('dropdown')
            . ' style="display:none;position:absolute;top:100%;left:42px;right:0;background:#fff;'
            . 'border:1px solid #dee2e6;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.1);'
            . 'max-height:200px;overflow-y:auto;z-index:1000;margin-top:2px;"></div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Queue the picker behaviour on the current page (once).
     *
     * @return void
     */
    public static function require_js(): void {
        global $PAGE;
        if (self::$jsqueued) {
            return;
        }
        self::$jsqueued = true;
        $PAGE->requires->js_init_code(self::js());
    }

    /**
     * The picker behaviour.
     *
     * @return string
     */
    private static function js(): string {
        $icons = json_encode(self::icons(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return <<<JS
window.iccaIconPicker = window.iccaIconPicker || (function() {
    var icons = {$icons}.slice().sort();

    function cssClass(ic) {
        ic = (ic || '').trim();
        if (!ic) { return ''; }
        return (ic.indexOf('fa-solid') === 0 || ic.indexOf('fa ') === 0) ? ic : 'fa ' + ic;
    }
    function parts(input) {
        var wrap = input.closest('.icca-iconpicker');
        return {
            dd: wrap ? wrap.querySelector('.icca-iconpicker-dd') : null,
            preview: wrap ? wrap.querySelector('.icca-iconpicker-preview i') : null
        };
    }
    function sync(input) {
        var v = input.value.trim();
        var target = null;
        if (input.dataset.syncId) {
            target = document.getElementById(input.dataset.syncId);
        } else if (input.dataset.syncName) {
            target = document.querySelector('[name="' + input.dataset.syncName + '"]');
        }
        if (target) { target.value = v; }
        var p = parts(input);
        if (p.preview) { p.preview.className = cssClass(v); }
    }
    function show(input) {
        var dd = parts(input).dd;
        if (!dd) { return; }
        // Compare names without their "fa-"/"fa-solid " prefixes on both sides, so a field
        // already holding a full name (e.g. "fa-book") still lists matches when reopened.
        var q = input.value.toLowerCase().trim().replace(/^fa-solid /, '').replace(/^fa /, '').replace(/^fa-/, '');
        var matches = q === '' ? icons : icons.filter(function(ic) {
            return ic.replace('fa-solid ', '').replace('fa-', '').indexOf(q) !== -1;
        });
        dd.innerHTML = '';
        if (!matches.length) { dd.style.display = 'none'; return; }
        matches.forEach(function(ic) {
            var row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:7px 12px;cursor:pointer;'
                + 'font-size:1rem;font-family:monospace;border-bottom:1px solid #f0f0f0;';
            var i = document.createElement('i');
            i.className = cssClass(ic);
            i.setAttribute('aria-hidden', 'true');
            i.style.cssText = 'width:20px;text-align:center;font-size:16px;color:#495057;';
            var label = document.createElement('span');
            label.textContent = ic;
            row.appendChild(i);
            row.appendChild(label);
            row.addEventListener('mousedown', function(e) {
                e.preventDefault();
                input.value = ic;
                sync(input);
                dd.style.display = 'none';
            });
            row.addEventListener('mouseenter', function() { this.style.background = '#f8f9fa'; });
            row.addEventListener('mouseleave', function() { this.style.background = ''; });
            dd.appendChild(row);
        });
        dd.style.display = 'block';
    }

    document.addEventListener('click', function(e) {
        document.querySelectorAll('.icca-iconpicker').forEach(function(wrap) {
            if (!wrap.contains(e.target)) {
                var dd = wrap.querySelector('.icca-iconpicker-dd');
                if (dd) { dd.style.display = 'none'; }
            }
        });
    });

    return {
        input: function(el) { sync(el); show(el); },
        open: function(el) { show(el); },
        close: function(el) { var dd = parts(el).dd; if (dd) { dd.style.display = 'none'; } }
    };
})();
JS;
    }
}