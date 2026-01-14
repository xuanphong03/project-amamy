<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Register all actions and filters for the plugin
 */
class Delivery_Order_System_Plugin_Loader
{
    /**
     * Array of actions to register
     *
     * @var array
     */
    protected $actions;

    /**
     * Array of filters to register
     *
     * @var array
     */
    protected $filters;

    /**
     * Initialize collections
     */
    public function __construct()
    {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * Add a new action to the collection
     *
     * @param string $hook          The name of the WordPress action
     * @param object $component     A reference to the instance of the object on which the action is defined
     * @param string $callback      The name of the function definition on the $component
     * @param int    $priority      Optional. Priority at which the function should be fired. Default 10
     * @param int    $accepted_args Optional. Number of arguments that should be passed to the $callback. Default 1
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection
     *
     * @param string $hook          The name of the WordPress filter
     * @param object $component     A reference to the instance of the object on which the filter is defined
     * @param string $callback      The name of the function definition on the $component
     * @param int    $priority      Optional. Priority at which the function should be fired. Default 10
     * @param int    $accepted_args Optional. Number of arguments that should be passed to the $callback. Default 1
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Utility function to register actions and filters
     *
     * @param array  $hooks         Collection of hooks (actions or filters)
     * @param string $hook          The name of the WordPress hook
     * @param object $component     A reference to the instance of the object on which the hook is defined
     * @param string $callback      The name of the function definition on the $component
     * @param int    $priority      Priority at which the function should be fired
     * @param int    $accepted_args Number of arguments that should be passed to the $callback
     * @return array                Collection of hooks
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args)
    {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress
     */
    public function run()
    {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }
}
