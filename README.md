# microcoso

Microcoso is a VERY BASIC cms written in PHP.

It was developed for personal use and is freely accessible from my GitHub for anyone who wants to reuse or modify it.

The code is not yet mature and NO WARRANTY IS PROVIDED. 
It may contain security flaws, so USE IT AT YOUR OWN RISK.

Currently in italian, but I'll be translating it soon.

## How it works

### Setup

1. Place index.php in the root directory of your website
2. Edit index.php as needed to customize your site (styling, configuration, etc.)

### Directory Structure

The CMS uses a simple folder organization:

- **content/** - Store your blog posts here as .txt files
- **img/** - Place all images used in your posts here
- **css/** - Stylesheets for light and dark themes
- **templates/** - (Optional) Example templates and sample post files for reference; not required by the CMS.

> Note: The CMS renders posts using the built-in logic in `index.php`; templates are not loaded automatically.

### Writing Posts

Create .txt files in the content/ folder to publish new posts. Each post supports the following syntax:

#### Post Metadata

\\\
[title]=Your Post Title
[date]=2025-12-18
[author]=Author Name
\\\

| Field | Syntax | Notes |
|-------|--------|-------|
| Title | \[title]=Post Title\ | Required for the post to appear |
| Date | \[date]=YYYY-MM-DD\ | Used for sorting posts chronologically |
| Author | \[author]=Author Name\ | If omitted, displays "Autore Sconosciuto" (Unknown Author) |

#### Content Elements

```
[image: Image description | img/imagefile.png]
[link: Link text | https://example.com/]
```

#### Text Formatting

```
**bold text**
*italic text*
```

#### Headings

```
## Heading 2
### Heading 3
#### Heading 4
##### Heading 5
###### Heading 6
```

#### Lists

**Unordered list:**
```
- Item 1
- Item 2
- Item 3
```

**Ordered list:**
```
1. First item
2. Second item
3. Third item
```

#### Code

**Inline code:**
```
Use `variable_name` in your text
```

**Code block:**
````
```
function example() {
  return "Hello World";
}
```
````

### Example

See `content/post1.txt` and `content/post2.txt` for complete examples of properly formatted posts.

### Styling

The CMS includes two theme options:
- **Light theme** (`css/light.css`)
- **Dark theme** (`css/dark.css`)
