import re

less = open('assets/default/less/ticket-forms.less').read()

replacement = '''#ticketForm tbody tr {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  border-bottom: 1px solid var(--border-light);
}
#ticketForm tbody tr:last-child {
  border-bottom: none;
}

#ticketForm tbody tr td {
  padding: var(--space-3) var(--space-4);
  display: block;
}

#ticketForm tbody tr td:first-child {
  width: 25% !important;
  font-weight: 600;
  color: var(--text-secondary);
  font-size: var(--text-sm);
}

#ticketForm tbody tr td:nth-child(2) {
  width: 75% !important;
  flex: 1;
  min-width: 0; /* Prevent flex overflow */
}'''

pattern = re.compile(r'#ticketForm tbody tr \{.*?padding-bottom: 0;\n\}', re.DOTALL)
if pattern.search(less):
    less = pattern.sub(replacement, less)
else:
    print('Failed to match ticketForm tr block')

# Make sure we add mobile stacking for the flex layout
media_replacement = '''@media (max-width: 768px) {
  #ticketForm tbody tr {
    flex-direction: column;
    align-items: flex-start;
  }
  #ticketForm tbody tr td:first-child,
  #ticketForm tbody tr td:nth-child(2) {
    width: 100% !important;
  }
  #ticketForm tbody tr td:first-child {
    padding-bottom: 0;
  }
  #ticketForm tbody tr td {
    padding: var(--space-2) var(--space-3);
  }'''

media_pattern = re.compile(r'@media \(max-width: 768px\) \{\n  #ticketForm tbody tr td \{.*?\n  \}', re.DOTALL)
if media_pattern.search(less):
    less = media_pattern.sub(media_replacement, less)
else:
    print('Failed to match media block')

open('assets/default/less/ticket-forms.less', 'w').write(less)
print('Updated ticket-forms.less')
