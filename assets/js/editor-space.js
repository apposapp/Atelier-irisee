/**
 * TinyMCE plugin: the "Empty line" button (Visual tab). Inserts an empty paragraph that WordPress keeps.
 * The label comes from the editor settings (class-aimp-editor.php).
 */
(function () {
	'use strict';

	if (!window.tinymce) {
		return;
	}

	window.tinymce.PluginManager.add('aimpspace', function (editor) {
		var label = editor.getParam('aimpspace_label') || 'Empty line';
		var html = '<p class="aimp-space">&nbsp;</p>'; // Same as AIMP_Editor::SPACE_HTML.

		editor.addButton('aimpspace', {
			text: '↵',
			tooltip: label,
			onclick: function () {
				editor.insertContent(html);
			}
		});
	});
})();
