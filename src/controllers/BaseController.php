<?php

namespace chasegiunta\inertia\controllers;

use Craft;
use craft\web\Controller as Controller;
use yii\web\Response;
use craft\web\View;
use craft\web\UrlManager;
use craft\helpers\ElementHelper;

use chasegiunta\inertia\Plugin as Inertia;

/**
 * Controller controller
 */
class BaseController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * inertia/controller action
     */

    public function actionIndex(): array|string
    {
        $request = Craft::$app->getRequest();
        $uri = $request->getPathInfo();

        $urlManager = new UrlManager();
        $element = $urlManager->getMatchedElement();

        $templateVariables = [];
        $matchesTwigTemplate = false;

        if ($element) {
            [$matchesTwigTemplate, $specifiedTemplate, $templateVariables] = $this->handleElementRequest($element, $uri);
        } else {
            $inertiaConfiguredDirectory = Inertia::getInstance()->settings->inertiaDirectory ?? null;
            $inertiaTemplatePath = $inertiaConfiguredDirectory ? $inertiaConfiguredDirectory . '/' . $uri : $uri;

            $matchesTwigTemplate = Craft::$app->getView()->doesTemplateExist($inertiaTemplatePath);
        }

        if ($matchesTwigTemplate) {
            $template = $specifiedTemplate ?? $inertiaTemplatePath;

            $stringResponse = Craft::$app->getView()->renderTemplate($template, $templateVariables);

            // Decode JSON object from $stringResponse
            $jsonData = json_decode($stringResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to decode JSON response: ' . json_last_error_msg());
            }

            $component = $jsonData['component'];
            $props = $jsonData['props'] ?? [];


            if (Inertia::getInstance()->settings->injectElement !== true) {
                unset($templateVariables['element']);
            }

            // Merge $props with $templateVariables, $props takes precedence
            $props = array_merge($templateVariables, $props);

            // $actuallyReturnTheTemplate = $this->renderTemplate($template);
            return $this->render($component, params: $props);
        } else {
            exit('No matching template found.');
        }
    }



    private ?string $only = '';


    /*
     * Capture request for partial reload
     */
    public function beforeAction($action): bool
    {
        if (Craft::$app->request->headers->has('X-Inertia-Partial-Data')) {
            $this->only = Craft::$app->request->headers->get('X-Inertia-Partial-Data');
        }

        return true;
    }

    /**
     * @param string $view
     * @param array $params
     * @return array|string
     */
    public function render($view, $params = []): array|string
    {
        // Set params as expected in Inertia protocol
        // https://inertiajs.com/the-protocol
        $params = [
            'component' => $view,
            'props' => $this->getInertiaProps($params),
            'url' => $this->getInertiaUrl(),
            'version' => $this->getInertiaVersion()
        ];

        // XHR-Request: just return params
        if (Craft::$app->request->headers->has('X-Inertia')) {
            return $params;
        }

        $inertiaDirectory = Inertia::getInstance()->settings->inertiaDirectory;
        $baseView = Inertia::getInstance()->settings->view;
        $template = $inertiaDirectory ? $inertiaDirectory . '/' . $baseView : $baseView;

        // First request: Return full template
        return Craft::$app->view->renderTemplate($template, [
            'page' => $params
        ]);
    }

    /**
     * Merge shared props and individual request props
     *
     * @param array $params
     * @return array
     */
    private function getInertiaProps($params = []): array
    {
        $sharedProps = $this->getSharedPropsFromTemplate();
        return array_merge(
            Inertia::getInstance()->getShared(),
            $params,
            $sharedProps
        );
    }

    /**
     * Request URL
     *
     * @return string
     */
    private function getInertiaUrl(): string
    {
        return Craft::$app->request->getUrl();
    }

    /**
     * Asset version finger print
     *
     * @return string
     */
    private function getInertiaVersion(): string
    {
        return Inertia::getInstance()->getVersion();
    }

    /*
     * Check if prop was requested in partial reload
     */
    public function checkOnly($key): bool
    {
        return in_array($key, explode(',', $this->only), true);
    }

    /*
     * Get all props requested in partial reload (comma separated string)
     */
    public function getOnly(): ?string
    {
        return $this->only;
    }

    private function extractUriParameters($uri, $uriFormat)
    {
        // Convert the format into a regex pattern
        $pattern = preg_replace('/\{([^}]+)\}/', '([^\/]+)', $uriFormat);
        $pattern = str_replace('/', '\/', $pattern);
        $pattern = '#^' . $pattern . '$#'; // Using # as delimiter instead of /

        // Extract parameter names from the format
        preg_match_all('/\{([^}]+)\}/', $uriFormat, $paramNames);
        $paramNames = $paramNames[1];

        // Extract values from the URI
        preg_match($pattern, $uri, $matches);

        // Remove the full match
        array_shift($matches);

        // Combine parameter names with their values
        $parameters = array_combine($paramNames, $matches);

        return $parameters;
    }

    private function handleElementRequest($element, $uri)
    {
        $section = $element->getSection();

        $site = Craft::$app->getSites()->getCurrentSite();
        $siteID = $site->id;

        /** @var array $sectionSiteSettings */
        $sectionSiteSettings = $section->getSiteSettings();
        $sectionSiteSetting = null;
        foreach ($sectionSiteSettings as $setting) {
            if ($setting->siteId === $siteID) {
                $sectionSiteSetting = $setting;
                break;
            }
        }

        if ($sectionSiteSetting === null) {
            throw new \Exception('No section site setting found for the current site.');
        }

        $uriFormat = $sectionSiteSetting->uriFormat;

        $specifiedTemplate = $sectionSiteSetting->template;
        $templateVariables = $this->extractUriParameters($uri, $uriFormat);
        $templateVariables['element'] = $element;

        $matchesTwigTemplate = Craft::$app->getView()->doesTemplateExist($specifiedTemplate);
        return [$matchesTwigTemplate, $specifiedTemplate, $templateVariables];
    }

    private function getSharedPropsFromTemplate()
    {
        $inertiaConfiguredDirectory = Inertia::getInstance()->settings->inertiaDirectory ?? null;
        $inertiaTemplatePath = $inertiaConfiguredDirectory ? $inertiaConfiguredDirectory . '/shared' : 'shared';

        $matchesTwigTemplate = Craft::$app->getView()->doesTemplateExist($inertiaTemplatePath);

        if (!$matchesTwigTemplate) {
            return [];
        }

        $stringResponse = Craft::$app->getView()->renderTemplate($inertiaTemplatePath);

        // Decode JSON object from $stringResponse
        $jsonData = json_decode($stringResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to decode JSON response: ' . json_last_error_msg());
        }

        return $jsonData;
    }

}
