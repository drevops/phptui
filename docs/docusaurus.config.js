// @ts-check
// `@type` JSDoc annotations allow editor autocompletion and type checking
// (when paired with `@ts-check`).
// There are various equivalent ways to declare your Docusaurus config.
// @see https://docusaurus.io/docs/api/docusaurus-config

import {themes as prismThemes} from 'prism-react-renderer';

// The packaged One themes flatten variables, operators and functions into one
// blue; upstream One Dark/Light gives variables their own red, which keeps
// '$var->method()' chains readable. A later styles entry wins, so appending
// the variable override restores the upstream distinction.
const oneDark = {...prismThemes.oneDark, styles: [...prismThemes.oneDark.styles, {types: ['variable'], style: {color: 'hsl(355, 65%, 65%)'}}]};
const oneLight = {...prismThemes.oneLight, styles: [...prismThemes.oneLight.styles, {types: ['variable'], style: {color: 'hsl(5, 74%, 59%)'}}]};

/** @type {import('@docusaurus/types').Config} */
const config = {
  // The official slogan: it suffixes every page's <title> and stands alone as
  // the homepage title; the navbar keeps its own short 'TUI' brand.
  title: 'TUI - Terminal user interfaces for PHP',
  tagline: 'Terminal user interfaces for PHP',
  favicon: 'img/logo.svg',

  // Set the production url of your site here.
  url: 'https://phptui.dev',
  // Set the /<baseUrl>/ pathname under which your site is served.
  // For GitHub pages deployment, it is often '/<projectName>/'.
  baseUrl: '/',

  // GitHub pages deployment config.
  organizationName: 'drevops',
  projectName: 'tui',

  onBrokenLinks: 'throw',

  // Serve the generated widget SVGs (docs/assets) and the architecture diagrams
  // (docs/architecture) as static files alongside docs/static, so the pages can
  // reference them in place - without relocating the asset-generation pipeline
  // or the diagram sources maintained by the render-tui-diagrams skill.
  staticDirectories: ['static', 'assets', 'architecture'],

  // Even if you don't use internationalization, you can use this field to set
  // useful metadata like html lang. For example, if your site is Chinese, you
  // may want to replace "en" with "zh-Hans".
  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          routeBasePath: '/',
          sidebarPath: './sidebars.js',
          path: 'content',
          editUrl: 'https://github.com/drevops/tui/tree/main/docs/',
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
        gtag: {
          trackingID: 'G-9LM6X8F3XL',
          anonymizeIP: true,
        },
      }),
    ],
  ],

  markdown: {
    mermaid: true,
    hooks: {
      onBrokenMarkdownLinks: 'warn',
    },
  },

  plugins: [
    [
      '@docusaurus/plugin-client-redirects',
      {
        // Pages absorbed into larger ones keep their old URLs working.
        redirects: [
          {from: '/discovery', to: '/field-behaviour'},
          {from: '/self-describing-answers', to: '/headless-collection'},
        ],
      },
    ],
  ],

  themes: [
    [
      '@easyops-cn/docusaurus-search-local',
      /** @type {import("@easyops-cn/docusaurus-search-local").PluginOptions} */
      ({
        // @see https://github.com/easyops-cn/docusaurus-search-local#theme-options
        searchBarPosition: 'left',
        docsDir: 'content',
        docsRouteBasePath: '/',
        indexBlog: false,
        hashed: true,
        highlightSearchTermsOnTargetPage: true,
        explicitSearchResultPath: true,
      }),
    ],
    '@docusaurus/theme-mermaid',
  ],

  themeConfig:
  /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      // The social card lives in docs/assets (a static directory), generated
      // by docs/util/render-social-card.php as part of the asset pipeline.
      image: 'social-card.png',
      metadata: [
        {name: 'keywords', content: 'php, tui, terminal user interface, terminal forms, cli, console, prompts, interactive forms'},
      ],
      navbar: {
        title: '',
        logo: {
          alt: 'TUI Logo',
          src: 'img/logo.svg',
        },
        items: [
          {
            to: '/introduction',
            label: 'Docs',
            position: 'left',
          },
          {
            label: 'Download',
            href: 'https://github.com/drevops/tui/releases/latest',
            position: 'right',
            title: 'Download the latest version',
          },
          {
            href: 'https://github.com/drevops/tui',
            label: 'GitHub',
            position: 'right',
            title: 'View source on GitHub',
          },
          {
            type: 'search',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [
          {
            label: 'GitHub',
            href: 'https://github.com/drevops/tui',
          },
        ],
        copyright: `Version: ${process.env.RELEASE_VERSION || 'development'} <br/> Copyright ©${new Date().getFullYear()} DrevOps. Built with Docusaurus.`,
      },
      prism: {
        // One Dark sits on the same '#282c34' background as the dark
        // terminal recordings, so dark code blocks and recordings match;
        // One Light is its hue-matched twin for light mode.
        theme: oneLight,
        darkTheme: oneDark,
        additionalLanguages: ['php', 'bash'],
      },
      colorMode: {
        defaultMode: 'light',
        disableSwitch: false,
        respectPrefersColorScheme: true,
      },
    }),
};

export default config;
