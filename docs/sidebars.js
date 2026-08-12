// @ts-check

/**
 * The sidebar is a set of always-visible sections: every top-level category is
 * non-collapsible, so the whole docs map is scannable without expanding
 * anything, and `custom.css` styles the category labels as section headings.
 * Ordering lives here alone - pages carry no `sidebar_position` frontmatter.
 *
 * @type {import('@docusaurus/plugin-content-docs').SidebarsConfig}
 */
const sidebars = {
  tutorialSidebar: [
    {
      type: 'category',
      label: 'Getting started',
      collapsible: false,
      items: ['index', 'installation', 'specification'],
    },
    {
      type: 'category',
      label: 'Forms',
      collapsible: false,
      items: ['panels', 'layouts', 'configuration', 'field-behaviour', 'markup', 'progress-row', 'progress', 'output', 'testing'],
    },
    {
      type: 'category',
      label: 'Fields',
      collapsible: false,
      items: [
        {type: 'doc', id: 'fields/index', label: 'Overview'},
        'fields/calendar',
        'fields/confirm',
        'fields/filepicker',
        'fields/number',
        'fields/option-groups',
        'fields/password',
        'fields/pause',
        'fields/rating',
        'fields/reorder',
        'fields/search',
        'fields/select',
        'fields/suggest',
        'fields/template',
        'fields/text',
        'fields/textarea',
        'fields/toggle',
      ],
    },
    {
      type: 'category',
      label: 'Automation',
      collapsible: false,
      items: ['headless-collection', 'ai-agents'],
    },
    {
      type: 'category',
      label: 'Customization',
      collapsible: false,
      items: ['anatomy', 'themes', 'display-modes', 'markdown', 'key-bindings', 'translations'],
    },
    {
      type: 'category',
      label: 'About',
      collapsible: false,
      items: ['playground', 'architecture', 'contributing'],
    },
  ],
};

export default sidebars;
