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
      items: ['index', 'installation'],
    },
    {
      type: 'category',
      label: 'Forms',
      collapsible: false,
      items: ['panels', 'configuration', 'field-behaviour', 'progress', 'testing'],
    },
    {
      type: 'category',
      label: 'Widgets',
      collapsible: false,
      items: [
        {type: 'doc', id: 'widgets/index', label: 'Overview'},
        'widgets/calendar',
        'widgets/confirm',
        'widgets/filepicker',
        'widgets/number',
        'widgets/option-groups',
        'widgets/password',
        'widgets/pause',
        'widgets/progress',
        'widgets/reorder',
        'widgets/search',
        'widgets/select',
        'widgets/suggest',
        'widgets/text',
        'widgets/textarea',
        'widgets/toggle',
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
      items: ['themes', 'display-modes', 'key-bindings', 'translations'],
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
