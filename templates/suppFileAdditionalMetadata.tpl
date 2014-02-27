{**
 * plugins/generic/dataverse/templates/suppFileAdditionalMetadata.tpl
 *
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Dataverse plugin: data publication options
 *
 *}
 
{url|assign:"dataverseTermsOfUseUrl" page="dataverse" op="termsOfUse"}

<div id="dataverse">
  <h3>{translate key="plugins.generic.dataverse.suppFile.title"}</h3>
  <p>{translate key="plugins.generic.dataverse.suppFile.description"}</p>

  <input type="radio" name="dataPublishOpts" id="dataPublishOpts-1" value="1" checked="checked"/>
  <label for="dataPublishOpts-1">{translate key="plugins.generic.dataverse.suppFile.noDeposit"}</label>
  <br/>
  <input type="radio" name="dataPublishOpts" id="dataPublishOpts-2" value="2"/>
  <label for="dataPublishOpts-2">{translate key="plugins.generic.dataverse.suppFile.depositDataverse" dataverseTermsOfUseUrl=$dataverseTermsOfUseUrl}</label>
  <br/>
  <input type="radio" name="dataPublishOpts" id="dataPublishOpts-3" value="3"/>
  <label for="dataPublishOpts-3">{translate key="plugins.generic.dataverse.suppFile.externalCitation"}</label>
</div>
 <div class="separator"></div>
 