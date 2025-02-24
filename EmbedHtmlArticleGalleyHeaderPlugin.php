<?php

/**
 * @file plugins/generic/embedHtmlArticleGalleyHeader/EmbedHtmlArticleGalleyHeaderPlugin.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EmbedHtmlArticleGalleyHeaderPlugin
 * @ingroup plugins_generic_embedHtmlArticleGalleyHeader
 *
 * @brief Class for EmbedHtmlArticleGalleyHeader plugin
 */

namespace APP\plugins\generic\embedHtmlArticleGalleyHeader;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\observers\events\UsageEvent;
use APP\publication\Publication;
use APP\template\TemplateManager;
use DOMDocument;
use Exception;
use PKP\config\Config;
use PKP\core\PKPApplication;
use PKP\galley\Galley;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\submissionFile\SubmissionFile;

class EmbedHtmlArticleGalleyHeaderPlugin extends GenericPlugin
{
    public $application;

    public function __construct()
    {
        parent::__construct();
        $this->application = Application::get()->getName();
    }

    /**
         * @copydoc Plugin::register()
         * @param $category
         * @param $path
         * @param $mainContextId
         * @return bool
         */    

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            Hook::register('ArticleHandler::view::galley', array($this, 'articleViewCallback'), HOOK_SEQUENCE_LATE);
            $this->_registerTemplateResource();
        }
        return $success;
    }

    /**
     * Get the display name of this plugin.
     * @return String
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.embedHtmlArticleGalleyHeader.displayName');
    }

    /**
     * Get a description of the plugin.
     */
    public function getDescription(): string
    {
        return __('plugins.generic.embedHtmlArticleGalleyHeader.description');
    }

    /**
     * Present the article wrapper page.
     * @param string $hookName
     * @param array $args
     * @return bool
     */
    function articleViewCallback($hookName, $args): bool 
    {
        $request =& $args[0];
        $issue =& $args[1];
        $galley =& $args[2];
        $article =& $args[3];

        if (!$galley) {
            return false;
        }

        $submissionFile = $galley->getFile();
        if ($submissionFile->getData('mimetype') === 'text/html') {
            $fileId = $galley->getData('submissionFileId');
            if (!Hook::call('HtmlArticleGalleyPlugin::articleDownload', array($article,  &$galley, &$fileId))) {
                foreach ($article->getData('publications') as $publication) {
                    if ($publication->getId() === $galley->getData('publicationId')) {
                        $galleyPublication = $publication;
                        break;
                    }
                }
                    
                $templateMgr = TemplateManager::getManager($request);
                $html = $this->_getHTMLContents($request, $galley);

                $errorsEnabled = libxml_use_internal_errors();
                libxml_use_internal_errors(true);
                $dom = new DOMDocument();

                if (Config::getVar('i18n', 'client_charset') === "utf-8") {
                    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                } else {
                    $dom->loadHTML($html);
                }

                if ($dom->getElementsByTagName('body')->length != 0) {

                    $bodyElement = $dom->getElementsByTagName('body')->item(0);
                    $body = "";
                    foreach ($bodyElement->childNodes as $childNode) {
                        if ($childNode->nodeName != 'style') {
                            $body .= $dom->saveHTML($childNode);
                        }
                    }                

                    if ($dom->getElementsByTagName('head')->length != 0) {
                        $head = $dom->getElementsByTagName('head')->item(0);
                        if ($head->getElementsByTagName('link')->length != 0) {
                            $links = $head->getElementsByTagName("link");
                            $count = 0;
                            foreach($links as $l) {
                                if($l->getAttribute("rel") == "stylesheet") {
                                    $templateMgr->addHeader('embedStylesheet'. $count .'', '<link rel="stylesheet" type="text/css" href="' . $l->getAttribute("href") . '">');
                                    $count++;
                                }
                            }
                        }
                        if ($head->getElementsByTagName('script')->length != 0) {
                            $scripts = $head->getElementsByTagName("script");
                            $count = 0;
                            foreach($scripts as $script) {
                                    if(stristr($script->getAttribute("src"), '.js')) {
                                        $templateMgr->addHeader('embedJs'. $count .'', '<script type="text/javascript" src="' . $script->getAttribute("src") . '"></script>');
                                        $count++;
                                }
                            }
                    	}
                    }
                } else {
                    $body = $dom->savehtml(); 
                }

                $returner = true;
                Hook::call('HtmlArticleGalleyPlugin::articleDownloadFinished', array(&$returner));    
                $templateMgr->assign(array(
                    'issue' => $issue,
                    'article' => $article,
                    'galley' => $galley,
                    'isLatestPublication' => $article->getData('currentPublicationId') === $galley->getData('publicationId'),
                    'galleyPublication' => $galleyPublication,
                    'html' => $body,
                    'isFullWidth' => true,
                    'headerTemplatePath' => $this->getTemplateResource('header.tpl'),
                    'breadcrumbsTemplatePath' => $this->getTemplateResource('breadcrumbs_galley.tpl'),
                    'footerTemplatePath' => $this->getTemplateResource('footer.tpl')
                ));
                $templateMgr->display($this->getTemplateResource('display.tpl'));
                return true;
            }
        }

        return false;
    }

    /**
     * Return string containing the contents of the HTML file.
     * This function performs any necessary filtering, like image URL replacement.
     * @param $request PKPRequest
     * @param $galley ArticleGalley
     * @return string
     */
    protected function _getHTMLContents($request, $galley) {
        $submissionFile = $galley->getFile();
        $submissionId = $submissionFile->getData('submissionId');
        $contents = Services::get('file')->fs->read($submissionFile->getData('path'));
        
        // Replace media file references
        $embeddableFiles = Repo::submissionFile()
            ->getCollector()
            ->filterByAssoc(
                Application::ASSOC_TYPE_SUBMISSION_FILE,
                [$submissionFile->getId()]
            )
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_DEPENDENT])
            ->includeDependentFiles()
            ->getMany();
        
        $referredArticle = null;
        foreach ($embeddableFiles as $embeddableFile) {
            $params = array();

            if ($embeddableFile->getData('mimetype')=='text/plain' || $embeddableFile->getData('mimetype')=='text/css') $params['inline']='true';

            // Ensure that the $referredArticle object refers to the article we want
            if (!$referredArticle || $referredArticle->getId() != $submissionId) {
                $referredArticle = Repo::submission()->get($submissionId);
            }

            $fileUrl = $request->url(null, 'article', 'download', array($referredArticle->getBestArticleId(), $galley->getBestGalleyId(), $embeddableFile->getId()), $params);
            $pattern = preg_quote(rawurlencode($embeddableFile->getLocalizedData('name')), '/');

            $contents = preg_replace(
                '/([Ss][Rr][Cc]|[Hh][Rr][Ee][Ff]|[Dd][Aa][Tt][Aa])\s*=\s*"([^"]*' . $pattern . ')"/',
                '\1="' . $fileUrl . '"',
                $contents
            );
            if ($contents === null) {
                error_log('PREG error in ' . __FILE__ . ' line ' . __LINE__ . ': ' . preg_last_error());            }

            // Replacement for Flowplayer or other Javascript
            $contents = preg_replace(
                '/[Uu][Rr][Ll]\s*\:\s*\'(' . $pattern . ')\'/',
                'url:\'' . $fileUrl . '\'',
                $contents
            );
            if ($contents === null) {
                error_log('PREG error in ' . __FILE__ . ' line ' . __LINE__ . ': ' . preg_last_error());            }

            // Replacement for other players (tested with odeo; yahoo and google player won't work w/ OJS URLs, might work for others)
            $contents = preg_replace(
                '/[Uu][Rr][Ll]=([^"]*' . $pattern . ')/',
                'url=' . $fileUrl ,
                $contents
            );
            if ($contents === null) {
                error_log('PREG error in ' . __FILE__ . ' line ' . __LINE__ . ': ' . preg_last_error());
            }
        }

        // Perform replacement for ojs://... URLs
        $contents = preg_replace_callback(
            '/(<[^<>]*")[Oo][Jj][Ss]:\/\/([^"]+)("[^<>]*>)/',
            array($this, '_handleOjsUrl'),
            $contents
        );
        if ($contents === null) {
                error_log('PREG error in ' . __FILE__ . ' line ' . __LINE__ . ': ' . preg_last_error());        }

        // Perform variable replacement for journal, issue, site info
        $issue = Repo::issue()->getBySubmissionId($submissionId);

        $journal = $request->getContext();
        $site = $request->getSite();

        $paramArray = array(
            'issueTitle' => $issue?$issue->getIssueIdentification():__('editor.article.scheduleForPublication.toBeAssigned'),
            'journalTitle' => $journal->getLocalizedName(),
            'siteTitle' => $site->getLocalizedTitle(),
            'currentUrl' => $request->getRequestUrl(),
        );

        foreach ($paramArray as $key => $value) {
            $contents = str_replace('{$' . $key . '}', $value, $contents);
        }

        return $contents;
    }

    function _handleOjsUrl($matchArray) {
        $request = Application::get()->getRequest();
        $url = $matchArray[2];
        $anchor = null;
        if (($i = strpos($url, '#')) !== false) {
            $anchor = substr($url, $i+1);
            $url = substr($url, 0, $i);
        }
        $urlParts = explode('/', $url);
        if (isset($urlParts[0])) switch(strtolower_codesafe($urlParts[0])) {
            case 'journal':
                $url = $request->url(
                isset($urlParts[1]) ?
                $urlParts[1] :
                $request->getRequestedJournalPath(),
                null,
                null,
                null,
                null,
                $anchor
                );
                break;
            case 'article':
                if (isset($urlParts[1])) {
                    $url = $request->url(
                            null,
                            'article',
                            'view',
                            $urlParts[1],
                            null,
                            $anchor
                    );
                }
                break;
            case 'issue':
                if (isset($urlParts[1])) {
                    $url = $request->url(
                            null,
                            'issue',
                            'view',
                            $urlParts[1],
                            null,
                            $anchor
                    );
                } else {
                    $url = $request->url(
                            null,
                            'issue',
                            'current',
                            null,
                            null,
                            $anchor
                    );
                }
                break;
            case 'sitepublic':
                array_shift($urlParts);
                import ('classes.file.PublicFileManager');
                $publicFileManager = new PublicFileManager();
                $url = $request->getBaseUrl() . '/' . $publicFileManager->getSiteFilesPath() . '/' . implode('/', $urlParts) . ($anchor?'#' . $anchor:'');
                break;
            case 'public':
                array_shift($urlParts);
                $journal = $request->getJournal();
                import ('classes.file.PublicFileManager');
                $publicFileManager = new PublicFileManager();
                $url = $request->getBaseUrl() . '/' . $publicFileManager->getJournalFilesPath($journal->getId()) . '/' . implode('/', $urlParts) . ($anchor?'#' . $anchor:'');
                break;
        }
        return $matchArray[1] . $url . $matchArray[3];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\embedHtmlArticleGalleyHeader\EmbedHtmlArticleGalleyHeaderPlugin', '\EmbedHtmlArticleGalleyHeaderPlugin');
}
