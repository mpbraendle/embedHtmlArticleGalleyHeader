{**
 * templates/frontend/components/breadcrumbs_article.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Display a breadcrumb nav item showing the current page. This basic
 *  version is for top-level pages which only need to show the Home link. For
 *  category- and series-specific breadcrumb generation, see
 *  templates/frontend/components/breadcrumbs_catalog.tpl.
 *
 * @uses $article Article The current article.
 * @uses $issue Issue Issue this article was published in.
 * @uses $galley Galley Galley of the current article.
 *}

{if $galley->isPdfGalley()}
        {assign var="type" value="pdf"}
{else}
        {assign var="type" value="file"}
{/if}

<nav class="cmp_breadcrumbs" role="navigation" aria-label="{translate key="navigation.breadcrumbLabel"}">
	<ol>
		<li>
			<a href="{url page="index" router=$smarty.const.ROUTE_PAGE}">
				{translate key="common.homepageNavigationLabel"}
			</a>
			<span class="separator">{translate key="navigation.breadcrumbSeparator"}</span>
		</li>
		<li>
			<a href="{url router=$smarty.const.ROUTE_PAGE page="issue" op="archive"}">
				{translate key="navigation.archives"}
			</a>
			<span class="separator">{translate key="navigation.breadcrumbSeparator"}</span>
		</li>
		<li>
			{if isset($issue)}
				<a href="{url page="issue" op="view" path=$issue->getBestIssueId()}">
					{$issue->getIssueIdentification()}
				</a>
			{else}
				{translate key="plugins.generic.embedHtmlArticleGalleyHeader.preview"}
			{/if}
			<span class="separator">{translate key="navigation.breadcrumbSeparator"}</span>
		</li>
		<li>
			<a href="{url page="article" op="view" path=$article->getBestArticleId()}" class="title">
				{$article->getLocalizedTitle()|escape}
			</a>
			<span class="separator">{translate key="navigation.breadcrumbSeparator"}</span>
		</li>
		<li class="current" aria-current="page">
			<span class="galley_type {$type|escape}" aria-current="page">{translate key="plugins.generic.embedHtmlArticleGalleyHeader.galley_type.$type"}</span>
		</li>
	</ol>
</nav>
