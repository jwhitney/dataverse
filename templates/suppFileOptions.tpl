{**
 * plugins/generic/dataverse/templates/block.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Dataverse options for supplementary file form
 *
 *}

<div id="dataverse">
  <h3>{translate key="plugins.generic.dataverse.suppFile.title"}</h3>
  <p>{translate key="plugins.generic.dataverse.suppFile.description"}</p>

  <table width="100%">
    <tbody>
      <tr valign="top">
        <td width="20%">{translate key="plugins.generic.dataverse.dataCitation"}</td>
        <td>{if $dataCitation}{$dataCitation|strip_unsafe_html}{else}{translate key="plugins.generic.dataverse.suppFile.noSuppFilesInDataverse"}{/if}</td>
      </tr>
      <tr>
        <td>{translate key="plugins.generic.dataverse.suppFile.depositSuppFile"}</td>
        <td>
          {url|assign:"dataverseTermsOfUseUrl" page="dataverse" op="termsOfUse"}
          <input type="checkbox" name="depositSuppFile" id="depositSuppFile" value="1" {if $depositSuppFile}checked="checked"{/if}/>
          <label for="depositSuppFile">{translate key="plugins.generic.dataverse.suppFile.depositSuppFileDescription" dataverseTermsOfUseUrl=$dataverseTermsOfUseUrl}</label>
        </td>
      </tr>
    </tbody>
  </table>
      
      
</div>
 <div class="separator"> </div>
