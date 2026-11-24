/**
 * MOOP JBrowse plugin: "Open gene page" in the right-click (Mac: Control-click) menu of a gene.
 *
 * A JBrowse 2 "no-build" plugin in UMD form: a plain class exposed as
 * JBrowsePluginMoopGenePage = { default: Class }, loaded from config.plugins
 * (config/jbrowse2_plugins.json -> lib/jbrowse/PluginLoader.php). It extends the feature-track
 * display through JBrowse's documented extension point 'Core-extendPluggableElement', the same
 * way JBrowse's own docs add a context-menu item: wrap contextMenuItems(), keep every built-in
 * item, append one.
 *
 * Where the address comes from: the gene track's formatDetails (served by api/jbrowse2/config.php,
 * moop_jbrowse_gene_page_details()), which also puts a "Gene page" link in the details panel.
 * This plugin evaluates that same slot for the right-clicked feature and takes the anchor marked
 * data-moop-gene-page -- so the URL (site prefix, organism, assembly, gene set) is built once, on
 * the server, and the item appears ONLY on MOOP gene tracks (RNA-seq tracks have no such anchor).
 *
 * If a JBrowse upgrade changes the display model, this item disappears and the details-panel
 * link keeps working (it uses only the documented formatDetails slot). Check both after an upgrade.
 */
;(function () {
  'use strict'

  // Undo the server's htmlspecialchars() on the attributes we read.
  function decodeEntities(s) {
    return s
      .replace(/&quot;/g, '"')
      .replace(/&#0?39;/g, "'")
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&amp;/g, '&')
  }

  // The gene-page anchor in a formatDetails result, as {href, label}, or null.
  function findGenePageLink(details) {
    if (!details || typeof details !== 'object') return null
    for (const value of Object.values(details)) {
      if (typeof value !== 'string' || value.indexOf('data-moop-gene-page') === -1) continue
      const href = /href="([^"]*)"/.exec(value)
      const title = /title="([^"]*)"/.exec(value)
      if (href) {
        return { href: decodeEntities(href[1]), label: title ? decodeEntities(title[1]) : 'Open gene page' }
      }
    }
    return null
  }

  class MoopGenePagePlugin {
    constructor() {
      this.name = 'MoopGenePage'
      this.version = '1.0.0'
    }

    install(pluginManager) {
      pluginManager.addToExtensionPoint('Core-extendPluggableElement', pluggableElement => {
        if (pluggableElement.name !== 'LinearBasicDisplay' || !pluggableElement.stateModel) {
          return pluggableElement
        }
        const { readConfObject } = pluginManager.jbrequire('@jbrowse/core/configuration')
        pluggableElement.stateModel = pluggableElement.stateModel.extend(self => {
          const superContextMenuItems = self.contextMenuItems
          return {
            views: {
              contextMenuItems() {
                const items = superContextMenuItems()
                const feature = self.contextMenuFeature
                if (!feature) return items
                let link = null
                try {
                  const trackConf = self.parentTrack && self.parentTrack.configuration
                  if (trackConf) {
                    link = findGenePageLink(
                      readConfObject(trackConf, ['formatDetails', 'feature'], { feature: feature.toJSON() }),
                    )
                  }
                } catch (e) {
                  // Never break JBrowse's own menu over our one item.
                  console.error('MoopGenePage plugin:', e)
                }
                if (!link) return items
                return [
                  ...items,
                  {
                    label: link.label,
                    onClick: () => {
                      window.open(link.href, '_blank', 'noopener')
                    },
                  },
                ]
              },
            },
          }
        })
        return pluggableElement
      })
    }

    configure() {}
  }

  // JBrowse loads plugins in the page AND in its web workers, where there is no `window`.
  const root = typeof globalThis !== 'undefined' ? globalThis : typeof self !== 'undefined' ? self : window
  root.JBrowsePluginMoopGenePage = { default: MoopGenePagePlugin }
})()
