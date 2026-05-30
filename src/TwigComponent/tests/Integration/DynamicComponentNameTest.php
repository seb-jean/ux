<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\TwigComponent\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

/**
 * Tests for dynamic component names via {% component (expr) %} and <twig:component :is="...">.
 *
 * @author Sébastien Jean <sebastien.jean76@gmail.com>
 */
final class DynamicComponentNameTest extends KernelTestCase
{
    /**
     * {% component (variableName) %} renders the component whose name is held in the variable.
     */
    public function testDynamicComponentTagWithVariableName(): void
    {
        $this->assertStringContainsString(
            'Dynamic block content',
            self::render('dynamic_component_tag.html.twig')
        );
    }

    /**
     * {% component (variableName) %} also works with components that have block slots.
     */
    public function testDynamicComponentTagWithBlocks(): void
    {
        $this->assertStringContainsString(
            'Dynamic content with props',
            self::render('dynamic_component_tag_with_props.html.twig')
        );
    }

    /**
     * <twig:component :is="variable"> renders dynamically using the HTML syntax.
     */
    public function testDynamicHtmlSyntaxWithVariable(): void
    {
        $this->assertStringContainsString(
            'HTML syntax dynamic block content',
            self::render('dynamic_component_html_syntax.html.twig')
        );
    }

    /**
     * <twig:component is="StaticName"> renders with a static name in the HTML syntax.
     */
    public function testStaticHtmlSyntaxWithIsAttribute(): void
    {
        $this->assertStringContainsString(
            'HTML syntax static block content',
            self::render('dynamic_component_html_syntax_static.html.twig')
        );
    }

    /**
     * Self-closing <twig:component :is="variable" /> and <twig:component is="Name" /> work.
     */
    public function testSelfClosingHtmlSyntax(): void
    {
        $output = self::render('dynamic_component_self_closing_html.html.twig');

        // Both self-closing variants render the BasicComponent (div)
        $this->assertSame(2, substr_count($output, '<div>'));
    }

    /**
     * A parenthesized string literal expression compiles as a constant name.
     */
    public function testDynamicComponentTagWithStringLiteralExpression(): void
    {
        $this->assertStringContainsString(
            'from string expr',
            self::render('dynamic_component_string_expr.html.twig')
        );
    }

    /**
     * A parenthesized concatenation expression is evaluated at runtime.
     */
    public function testDynamicComponentTagWithConcatExpression(): void
    {
        $this->assertStringContainsString(
            'from concat expr',
            self::render('dynamic_component_concat_expr.html.twig')
        );
    }

    /**
     * The existing static syntaxes {% component 'Name' %} and {% component Name %} still work.
     */
    public function testStaticComponentTagSyntaxesStillWork(): void
    {
        $output = self::render('dynamic_component_static_syntaxes.html.twig');

        $this->assertStringContainsString('quoted', $output);
        $this->assertStringContainsString('bare', $output);
    }

    /**
     * {{ component(name) }} still works without regression.
     */
    public function testDynamicComponentFunctionStillWorks(): void
    {
        $output = self::render('dynamic_component_function_syntax.html.twig');

        $this->assertStringContainsString('<div>', $output);
    }

    /**
     * <twig:component> without an "is" attribute renders a component literally named "component" (backward compat).
     */
    public function testHtmlSyntaxWithoutIsAttributeRendersComponentNamedComponent(): void
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get(Environment::class);
        $twig->setLoader(new ArrayLoader());

        // Must compile without error — renders {{ component('component') }} internally
        $template = $twig->createTemplate('<twig:component />');
        $this->assertInstanceOf(\Twig\TemplateWrapper::class, $template);
    }

    /**
     * An array literal [] as a non-parenthesized component name is rejected at compile time.
     */
    public function testInvalidNonDynamicNameThrowsAtCompileTime(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Could not parse component name');

        /** @var Environment $twig */
        $twig = self::getContainer()->get(Environment::class);
        $twig->setLoader(new ArrayLoader());
        $twig->createTemplate('{% component [] %}{% endcomponent %}');
    }

    private static function render(string $template): string
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get(Environment::class);
        $twig->setCache(false);
        $twig->enableAutoReload();
        $twig->enableDebug();

        return $twig->render($template);
    }
}
