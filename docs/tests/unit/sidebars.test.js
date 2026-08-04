const fs = require('fs');
const path = require('path');
const sidebars = require('../../sidebars').default;

const contentDir = path.join(__dirname, '..', '..', 'content');

function collectSidebarDocIds(items) {
  const ids = [];

  for (const item of items) {
    if (typeof item === 'string') {
      ids.push(item);
      continue;
    }

    if (item.type === 'doc') {
      ids.push(item.id);
      continue;
    }

    if (item.type === 'category') {
      if (item.link && item.link.type === 'doc') {
        ids.push(item.link.id);
      }

      ids.push(...collectSidebarDocIds(item.items));
    }
  }

  return ids;
}

function collectContentDocIds(dir, prefix) {
  const ids = [];

  for (const entry of fs.readdirSync(dir, {withFileTypes: true})) {
    if (entry.isDirectory()) {
      ids.push(...collectContentDocIds(path.join(dir, entry.name), `${prefix}${entry.name}/`));
      continue;
    }

    if (/\.mdx?$/.test(entry.name)) {
      ids.push(prefix + entry.name.replace(/\.mdx?$/, ''));
    }
  }

  return ids;
}

describe('sidebars', () => {
  const sidebarIds = collectSidebarDocIds(sidebars.tutorialSidebar);
  const contentIds = collectContentDocIds(contentDir, '');

  test('lists every content page and nothing else', () => {
    expect([...sidebarIds].sort()).toEqual([...contentIds].sort());
  });

  test('references no doc id twice', () => {
    expect(new Set(sidebarIds).size).toBe(sidebarIds.length);
  });

  test('renders every top-level category as an always-visible section', () => {
    expect(sidebars.tutorialSidebar.map((item) => item.label)).toEqual(['Getting started', 'Forms', 'Fields', 'Automation', 'Customization', 'About']);

    for (const item of sidebars.tutorialSidebar) {
      expect(item.type).toBe('category');
      expect(item.collapsible).toBe(false);
    }
  });
});
