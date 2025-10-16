Get latest stable release from https://mozilla.github.io/pdf.js/

## CSS

### Renamespacing
Copy `viewer.css` to `wrapperviewer.scss`
To namespace all CSS variables, search and replace

`:root`

to

`&`

Change:

`[dir="rtl"]& {`

to

``&[dir="rtl"] {``

### Tidying
To allow SASS to compile, search and replace `:;` with `: ;`

### Compile

```sass wrappedviewer.scss > wrappedviewer.css```

### HTML

Take the contents of the body tag in view.html and drop it into the `div.modcoursework_pdfjswrapper` element in `viewpdf.mustache`

### JS

Set default options in web/viewer.mjs to:
```
defaultOptions.defaultUrl = {
    value: "",
    kind: OptionKind.VIEWER
};
defaultOptions.enableComment = {
    value: true,
    kind: OptionKind.VIEWER
};
```
