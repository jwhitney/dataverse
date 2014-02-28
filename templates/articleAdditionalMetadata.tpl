{**
 * plugins/generic/dataverse/templates/articleAdditionalMetadata.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Dataverse plugin: additonal submission metadata
 *}
<div id="dataversePlugin">
  <h3>{translate key="plugins.generic.dataverse.submissionMetadata.title"}</h3>
  <p>{translate key="plugins.generic.dataverse.submissionMetadata.description"}</p>
  <table width="100%" class="data">
    <tr valign="top">
      <td width="20%" class="label">{fieldLabel name="externalDataCitation" key="plugins.generic.dataverse.submissionMetadata.externalDataCitation"}</td>
      <td width="80%" class="value">
        <textarea cols="60" rows="15" class="textArea" id="externalDataCitation" name="externalDataCitation">{$externalDataCitation|escape}</textarea>
      </td>
    </tr>
  </table>  
</div>
 <div class="separator"> </div>