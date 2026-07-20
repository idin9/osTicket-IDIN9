import re

css = open('scp/css/modern/scp.css').read()

redactor_css = '''
/* Redactor editor improvements */
.redactor-box {
  border: 1px solid var(--input-border) !important;
  border-radius: var(--radius) !important;
  background: var(--input-bg) !important;
  overflow: hidden;
  box-shadow: none !important;
  margin-bottom: 0 !important;
}
.redactor-toolbar {
  background: var(--surface-1) !important;
  border-bottom: 1px solid var(--border) !important;
  box-shadow: none !important;
}
.redactor-editor {
  background: var(--input-bg) !important;
  color: var(--text-primary) !important;
  padding: var(--space-3) !important;
  font-family: inherit !important;
}
.redactor-editor:focus {
  outline: none;
}
.redactor-box.redactor-focus {
  border-color: var(--accent) !important;
  box-shadow: 0 0 0 3px var(--input-focus-ring) !important;
}

/* ==========================================================================
   Phase 3 — Queue enhancements (inbox-style, overflow actions)
'''

css = css.replace('/* ==========================================================================\n   Phase 3 — Queue enhancements (inbox-style, overflow actions)', redactor_css)
open('scp/css/modern/scp.css', 'w').write(css)
print('Updated scp.css with redactor CSS')
