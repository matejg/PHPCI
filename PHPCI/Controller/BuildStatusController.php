<?php
/**
 * PHPCI - Continuous Integration for PHP
 *
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Controller;

use b8;
use b8\Exception\HttpException\NotFoundException;
use b8\Store;
use PHPCI\BuildFactory;
use PHPCI\Model\Project;
use PHPCI\Model\Build;
use PHPCI\Service\BuildStatusService;

/**
 * Build Status Controller - Allows external access to build status information / images.
 * @author       Dan Cryer <dan@block8.co.uk>
 * @package      PHPCI
 * @subpackage   Web
 */
class BuildStatusController extends \PHPCI\Controller
{
    /* @var \PHPCI\Store\ProjectStore */
    protected $projectStore;

    /* @var \PHPCI\Store\BuildStore */
    protected $buildStore;

    /**
     * Initialise the controller, set up stores and services.
     */
    public function init()
    {
        $this->response->disableLayout();
        $this->buildStore = Store\Factory::getStore('Build');
        $this->projectStore = Store\Factory::getStore('Project');
    }

    /**
     * Returns status of the last build
     * @param $projectId
     * @return string
     */
    protected function getStatus($projectId)
    {
        $branch = $this->getParam('branch', 'master');
        $commitId = $this->getParam('commitId', NULL);

        try
        {
            $project = $this->projectStore->getById($projectId);
            if (!$project)
            {
                return NULL;
            }

            if (!$project->getAllowPublicStatus())
            {
                return NULL;
            }


            $build = $project->getLatestBuild($branch, NULL, $commitId);
            if (!$build)
            {
                return 'new-lightgrey';
            }

            switch ($build->getStatus())
            {
                case 0:
                    return 'pending-blue';
                case 1:
                    return 'running-yellow';
                case 2:
                    return 'passing-green';
                case 3:
                    return 'failed-red';
                default:
                    return 'error-red';
            }
        }
        catch (\Exception $e)
        {
            return 'error-red';
        }
    }

    /**
     * Returns the appropriate build status image in SVG format for a given project.
     */
    public function image($projectId)
    {
        $status = $this->getStatus($projectId);

        if (is_null($status))
        {
            $response = new b8\Http\Response\RedirectResponse();
            $response->setHeader('Location', '/');
            return $response;
        }

        $image = file_get_contents(__DIR__ . '/../../public/assets/badge/build-' . $status . '.svg');

        $this->response->disableLayout();
        $this->response->setHeader('Content-Type', 'image/svg+xml');
        $this->response->setContent($image);
        return $this->response;
    }

    /**
     * View the public status page of a given project, if enabled.
     * @param $projectId
     * @return string
     * @throws \b8\Exception\HttpException\NotFoundException
     */
    public function view($projectId)
    {
        $project = $this->projectStore->getById($projectId);

        if (empty($project))
        {
            throw new NotFoundException('Project with id: ' . $projectId . ' not found');
        }

        if (!$project->getAllowPublicStatus())
        {
            throw new NotFoundException('Project with id: ' . $projectId . ' not found');
        }

        $builds = $this->getLatestBuilds($projectId);

        if (count($builds))
        {
            $this->view->latest = $builds[0];
        }

        $this->view->builds = $builds;
        $this->view->project = $project;

        return $this->view->render();
    }

    /**
     * Render latest builds for project as HTML table.
     */
    protected function getLatestBuilds($projectId)
    {
        $criteria = ['project_id' => $projectId];
        $order = ['id' => 'DESC'];
        $builds = $this->buildStore->getWhere($criteria, 10, 0, [], $order);

        foreach ($builds['items'] as &$build)
        {
            $build = BuildFactory::getBuild($build);
        }

        return $builds['items'];
    }
}
