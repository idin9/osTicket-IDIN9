import re

css = open('scp/css/modern/scp.css').read()

# Replace the nav and sub_nav blocks
nav_replacement = '''/* ================= Navigation Overrides ================= */
#nav, #sub_nav {
  height: auto !important;
  line-height: normal !important;
  white-space: normal !important;
  border-left: none !important;
  border-right: none !important;
  padding: 0 var(--space-5) !important;
}

#nav {
  display: flex !important;
  gap: var(--space-2) !important;
  padding: var(--space-2) var(--space-5) 0 !important;
  background: var(--nav-bg) !important;
  border-bottom: 1px solid var(--border) !important;
  border-top: none !important;
  z-index: 200 !important;
}

#nav > li,
#nav .active, 
#nav .inactive {
  display: inline-flex !important;
  min-width: 0 !important;
  height: auto !important;
  border-radius: 0 !important;
  border: none !important;
  box-shadow: none !important;
  background: transparent !important;
  padding: 0 !important;
  margin: 0 !important;
}

#nav > li > a,
#nav .inactive a,
#nav .active a {
  display: inline-flex !important;
  align-items: center !important;
  min-height: var(--target-min) !important;
  padding: var(--space-2) var(--space-4) !important;
  font-size: var(--text-sm) !important;
  font-weight: 500 !important;
  color: var(--text-secondary) !important;
  text-decoration: none !important;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0 !important;
  border: 1px solid transparent !important;
  border-bottom: none !important;
  background: transparent !important;
  transition: background var(--transition), color var(--transition) !important;
  box-shadow: none !important;
  box-sizing: border-box !important;
}

#nav > li.active > a,
#nav .active a {
  color: var(--nav-active) !important;
  background: var(--surface-0) !important;
  border-color: var(--border) !important;
  position: relative !important;
  z-index: 2 !important;
}

#nav > li.active > a::after,
#nav .active a::after {
  content: "" !important;
  position: absolute !important;
  bottom: -2px !important;
  left: 0 !important;
  right: 0 !important;
  height: 3px !important;
  background: var(--surface-0) !important;
}

#nav > li.inactive:hover > a,
#nav .inactive a:hover {
  background: var(--surface-2) !important;
  color: var(--text-primary) !important;
}

#nav li.inactive > ul {
  width: 230px !important;
  background: var(--surface-0) !important;
  border: 1px solid var(--border) !important;
  border-radius: var(--radius-lg) !important;
  box-shadow: var(--shadow-lg) !important;
  padding: var(--space-2) 0 !important;
  z-index: 500 !important;
  top: 100% !important;
  left: 0 !important;
  margin-top: 1px !important;
  position: absolute !important;
}

#nav li.inactive > ul > li > a {
  padding: var(--space-2) var(--space-4) !important;
  border-radius: 0 !important;
  min-height: 0 !important;
  border: none !important;
}

#sub_nav {
  display: flex !important;
  flex-wrap: wrap !important;
  gap: var(--space-1) !important;
  padding: var(--space-2) var(--space-5) !important;
  background: var(--subnav-bg) !important;
  border-bottom: 1px solid var(--border) !important;
}

#sub_nav > li,
#sub_nav .active {
  display: inline-flex !important;
  border: none !important;
  background: transparent !important;
  padding: 0 !important;
  margin: 0 !important;
}

#sub_nav > li > a,
#sub_nav a {
  display: inline-flex !important;
  align-items: center !important;
  min-height: var(--target-aa) !important;
  padding: var(--space-1) var(--space-3) !important;
  font-size: var(--text-sm) !important;
  font-weight: 500 !important;
  color: var(--text-secondary) !important;
  border-radius: var(--radius-full) !important;
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
  text-decoration: none !important;
}

#sub_nav > li > a:hover,
#sub_nav a:hover {
  background: var(--surface-2) !important;
  color: var(--text-primary) !important;
}

#sub_nav > li.active > a,
#sub_nav > li > a.active,
#sub_nav a.active {
  background: var(--surface-0) !important;
  color: var(--nav-active) !important;
  box-shadow: var(--shadow-sm) !important;
}'''

pattern = re.compile(r'#nav, #sub_nav \{.*?#sub_nav > li > a\.active \{\n  background: var\(--surface-0\);\n  color: var\(--text-primary\);\n\}', re.DOTALL)
if pattern.search(css):
    css = pattern.sub(nav_replacement, css)
else:
    print('Failed to match nav block')

select_replacement = '''input[type="text"],
input[type="password"],
input[type="email"],
input[type="number"],
input[type="date"],
input[type="datetime-local"],
input[type="tel"],
input[type="url"],
input[type="search"],
textarea,
select,
.select2-container .select2-selection--single,
.select2-container .select2-selection--multiple {
  background: var(--input-bg) !important;
  color: var(--text-primary) !important;
  border: 1px solid var(--input-border) !important;
  border-radius: var(--radius) !important;
  padding: var(--space-2) var(--space-3) !important;
  font-size: var(--text-base) !important;
  font-family: inherit !important;
  line-height: 1.5 !important;
  transition: border-color var(--transition), box-shadow var(--transition) !important;
  box-sizing: border-box !important;
  height: auto !important;
  min-height: var(--target-min) !important;
}

select {
  max-width: none !important;
  padding-right: var(--space-8) !important;
  -webkit-appearance: none !important;
  appearance: none !important;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23475569' stroke-width='1.5' fill='none'/%3E%3C/svg%3E") !important;
  background-repeat: no-repeat !important;
  background-position: right var(--space-2) center !important;
}

.select2-container .select2-selection--single {
  display: flex !important;
  align-items: center !important;
  padding: 0 var(--space-3) !important;
}

.select2-container .select2-selection--single .select2-selection__rendered {
  padding: 0 !important;
  line-height: normal !important;
  color: var(--text-primary) !important;
}

.select2-container .select2-selection--single .select2-selection__arrow {
  height: 100% !important;
  right: var(--space-2) !important;
}'''

pattern2 = re.compile(r'input\[type="text"\],.*?background-position: right var\(--space-2\) center;\n\}', re.DOTALL)
if pattern2.search(css):
    css = pattern2.sub(select_replacement, css)
else:
    print('Failed to match select block')

open('scp/css/modern/scp.css', 'w').write(css)
print('Updated scp.css')
