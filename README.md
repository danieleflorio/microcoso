# microcoso

Microcoso is a VERY BASIC cms written in PHP.

It was developed for personal use and is freely accessible from my GitHub for anyone who wants to reuse or modify it.

The code is not yet mature and NO WARRANTY IS PROVIDED. 
It may contain security flaws, so USE IT AT YOUR OWN RISK.

Currently in italian, but I'll be translating it soon.

How it works:

You need to place the index.php file in the root directory of your website, editing it as necessary.

There are three folders:

- content: here you need to insert the posts in the form of text files
- img: the folder containing the images for the posts
- templates: currently contains a sample template for posts

Each text file (post) inserted in the “content” folder supports the following syntax:

[title]=Title of the post : to insert title

[date]=2025-12-18 : to insert date, will be used for sorting articles on the main page

[author]=Author's name : to insert the name of the author, if not present will be shown “Autore Sconosciuto” which is Italian for “unknown author”

[image: Image description | img/imagefile.png ] : to insert an image

[link: Link description | https://example.com/ ] to insert a link

See the provided examples post for reference.

