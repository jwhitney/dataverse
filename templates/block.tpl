{**
 * plugins/generic/dataverse/templates/block.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Dataverse block plugin
 *
 *}

<div class="block">
	<span class="blockTitle">{translate key="plugins.generic.dataverse.block.title"}</span>
	<p>{$dataCitation|strip_unsafe_html}</p>
</div>
