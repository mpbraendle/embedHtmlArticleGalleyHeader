{**
 * plugins/generic/embedHtmlArticleGalley/display.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Embedded viewing of a HTML galley.
 *}
{include file=$headerTemplatePath}
{include file=$breadcrumbsTemplatePath}
		<div id="htmlContainer">
			{$html}

			{* UZH CHANGE OJS-62 sui-generis script for numbering of paragraphs *}
			<script>
				$('.content p').each(function(){
					var nrid = $(this).attr('id');
					if (nrid) {
						var nrid2 = nrid.replace('nr-','');
						$(this).replaceWith('<p class="nr">'+nrid2+'</p>');
					}
				});
			</script>
			{* END CHANGE OJS-62 *}

			{* UZH CHANGE OJS-67 2019/03/08/mb display Licensing info *}
			{* Licensing info *}
			{if $copyright || $licenseUrl}
				<div class="item copyright">
					{if $licenseUrl}
						{if $ccLicenseBadge}
							{if $copyrightHolder}
								<p>{translate key="submission.copyrightStatement" copyrightHolder=$copyrightHolder copyrightYear=$copyrightYear}</p>
							{/if}
							{$ccLicenseBadge}
						{else}
							<a href="{$licenseUrl|escape}" class="copyright">
								{if $copyrightHolder}
									{translate key="submission.copyrightStatement" copyrightHolder=$copyrightHolder copyrightYear=$copyrightYear}
								{else}
									{translate key="submission.license"}
								{/if}
							</a>
						{/if}
					{/if}
				</div>
			{/if}
        		{* END UZH CHANGE OJS-67 *}
		</div>
	</div>

	{call_hook name="Templates::Common::Footer::PageFooter"}
	{include file=$footerTemplatePath}
</body>
</html>
