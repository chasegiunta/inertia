<?php

namespace chasegiunta\inertia\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{

    /** The template that will be rendered on first calls.
     *
     *  Includes the div the inertia app will be rendered to:
     *  <div id="app" data-page="{{ page|json_encode }}"></div>
     *
     * and calls the inertia js app
     * <script src="<path_to_app>/app.js"></script>
     *
     */
    public string $view = 'inertia/inertia.twig';

    /** The key the adapter uses for handling shared props */
    public string $shareKey = '__inertia__';

    /** whether inertia's assets versioning shall be used
     * Set to false if this is already handled in your build process
     */
    public bool $useVersioning = true;

    /** Array of directories that will be checked for changed assets if useVersioning = true
     *  Supports environment variables and aliases.
     */
    public array $assetsDirs = ['@webroot/assets'];

    /**
     * Whether to inject the element automatically into the frontend response
     * @var bool
     */
    public bool $injectElementAsProp = false;

    /**
     * The template directory where the Inertia backing logic is stored
     * @var string|null
     */
    public string|null $inertiaDirectory = null;

    /**
     * The path to a Shared backing template
     * @var string|null
     */
    public string|null $sharedPath = null;
}
