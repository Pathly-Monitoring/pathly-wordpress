#!/usr/bin/env node
// WordPress.org build only: translations come from translate.wordpress.org, so the
// directory review refuses shipped .po/.mo files and load_plugin_textdomain().
// The zip offered for direct download keeps them (nothing else delivers French there).
const fs = require("fs");
const path = require("path");

const root = process.argv[2];
if (!root) throw new Error("usage: strip-textdomain.js <plugin dir>");

function rewrite(file, change) {
  const full = path.join(root, file);
  const before = fs.readFileSync(full, "utf8");
  const after = change(before);
  if (after === before) throw new Error(`nothing to strip in ${file}`);
  fs.writeFileSync(full, after);
}

rewrite("includes/class-pathly-plugin.php", (s) =>
  s
    .replace(/^[^\n]*add_action\([^\n]*load_textdomain[^\n]*\r?\n/m, "")
    .replace(/\t\/\*\*(?:(?!\*\/)[\s\S])*?\*\/\r?\n\tpublic static function load_textdomain\(\) \{[\s\S]*?\r?\n\t\}\r?\n\r?\n/, ""),
);
rewrite("pathly.php", (s) => s.replace(/^ \* Domain Path:[^\n]*\r?\n/m, ""));
fs.rmSync(path.join(root, "languages"), { recursive: true });

const left = fs.readFileSync(path.join(root, "includes/class-pathly-plugin.php"), "utf8");
if (/load_plugin_textdomain|load_textdomain/.test(left)) throw new Error("textdomain loader still present");
