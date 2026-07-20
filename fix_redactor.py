import re

less = open('assets/default/less/ticket-forms.less').read()

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

/* Form action buttons area */
'''

less = less.replace('/* Form action buttons area */', redactor_css)
open('assets/default/less/ticket-forms.less', 'w').write(less)
print('Updated ticket-forms.less with redactor CSS')
