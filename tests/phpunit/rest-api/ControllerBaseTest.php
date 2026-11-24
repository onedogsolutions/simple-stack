<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */

/**
 * Class ControllerBaseTest
 *
 * Test common behavior of all rest api controllers
 */
class ControllerBaseTest extends WP_UnitTestCase
{
    protected WP_REST_Server $rest;
    protected SSTK_REST_API_Controller_Base $controller;
    protected string $resource_route;
    protected string $error_route;

    /**
     * Sets test controller and other convenience variables
     */
    public function setUp(): void
    {
        parent::setUp();
        require_once dirname(dirname(__DIR__)) . '/data/class-sstk-rest-api-controller-test.php';
        $this->controller = new SSTK_REST_API_Controller_Test();
        $this->rest = rest_get_server();
        $this->resource_route = '/' . $this->controller->get_namespace();
        $this->error_route = $this->resource_route . '/error';
        wp_set_current_user(1);
    }

    /**
     * Verifies all endpoints have been properly registered.
     */
    function test_it_registers_rest_routes()
    {
        $routes = $this->rest->get_routes();
        $this->assertArrayHasKey($this->resource_route, $routes);
        $this->assertArrayHasKey($this->error_route, $routes);
        $this->assertCount(4, $routes[$this->resource_route]);
    }

    /**
     * Verify basic GET route execution
     */
    function test_it_executes_a_get_route()
    {
        $request = new WP_REST_Request('GET', $this->resource_route);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals(['success' => true], $data);
    }

    /**
     * Verify basic POST route execution
     */
    function test_it_executes_a_post_route()
    {
        $request = new WP_REST_Request('POST', $this->resource_route);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals(['success' => true], $data);
    }

    /**
     * Verify basic PUT route execution
     */
    function test_it_executes_a_put_route()
    {
        $request = new WP_REST_Request('PUT', $this->resource_route);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals(['success' => true], $data);
    }

    /**
     * Verify basic DELETE route execution
     */
    function test_it_executes_a_delete_route()
    {
        $request = new WP_REST_Request('DELETE', $this->resource_route);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals(['success' => true], $data);
    }

    /**
     * Verifies that the user must be logged in and has admin permissions to make requests to the REST API.
     */
    function test_it_fails_with_incorrect_permissions()
    {
        wp_set_current_user(0);
        $request = new WP_REST_Request('GET', $this->resource_route);
        $error = $this->rest->dispatch($request)->as_error();
        $this->assertWPError($error);
        $this->assertEquals(['status' => 401], $error->get_error_data());
        wp_set_current_user($this->factory()->user->create());
        $error = $this->rest->dispatch($request)->as_error();
        $this->assertEquals(['status' => 403], $error->get_error_data());
    }

    /**
     * Verifies that React screen detection uses WordPress's actual submenu hook names.
     */
    function test_react_screen_hooks_match_wordpress_submenu_hooks()
    {
        $this->assertTrue(SSTK()->is_sstk_screen('toplevel_page_simplestack'));
        $this->assertTrue(SSTK()->is_sstk_screen('simple-stack_page_simplestack-sites'));
        $this->assertTrue(SSTK()->is_sstk_screen('simple-stack_page_simplestack-providers'));
        $this->assertTrue(SSTK()->is_sstk_screen('simple-stack_page_simplestack-activity'));
        $this->assertFalse(SSTK()->is_sstk_screen('sstk_page_simplestack-providers'));
        $this->assertFalse(SSTK()->is_sstk_screen('index.php'));
    }

    /**
     * Verifies credential-shaped presence flags remain booleans while secret values are masked.
     */
    function test_secret_redactor_preserves_presence_flags()
    {
        $data = SSTK_Secret_Redactor::redact([
            'credentials_available' => false,
            'uses_api_key' => false,
            'has_api_key' => true,
            'api_key' => 'secret-value',
            'root_password' => 'secret-value',
            'optional_token' => null,
        ]);

        $this->assertFalse($data['credentials_available']);
        $this->assertFalse($data['uses_api_key']);
        $this->assertTrue($data['has_api_key']);
        $this->assertNull($data['optional_token']);
        $this->assertSame(SSTK_Secret_Redactor::MASK, $data['api_key']);
        $this->assertSame(SSTK_Secret_Redactor::MASK, $data['root_password']);
    }

    /**
     * Verifies that exceptions and errors in endpoints get handled with a standard response.
     */
    function test_handles_exceptions_as_error_responses()
    {
        $request = new WP_REST_Request('GET', $this->error_route);
        $error = $this->rest->dispatch($request)->as_error();
        $this->assertWPError($error);
        $this->assertEquals(['status' => 400], $error->get_error_data());
        $request->set_param('disable_status_code', 1);
        $response = $this->rest->dispatch($request);
        $this->assertFalse($response->is_error());
        $this->assertEquals([
            'code' => 'sstk_rest_test_error',
            'message' => 'Test Error',
            'data' => ['status' => 200]
        ], $response->get_data());

    }
}