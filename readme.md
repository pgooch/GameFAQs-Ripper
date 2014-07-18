# GameFAQs Ripper

A random side project I made based on an idea this class contains everything needed to scrape the gamefaqs.com website for titles based on a advanced search and download all the box art for them then, combine all the boxart into an image of sorts.

This was done as two separate files `scrape.php` and `combine.php` because both the scrape and combine action can take considerable time and because I didn't want to repeatedly download all the images for the GameFAQs servers.

Some examples pulls are included in the repo, and the scrape and combine files are the same ones used to create them. 

This project was crated over about a day of work done here and there during downtime. While it is semi-documented is was done quickly while working on more important project and therefore it is rough around the edges. Some functions may have undocumented requirements. This should be considered an as-is example of a quick proof-of-concept.

_Note: There is little to no chance this will be updated in the future. While I have considered scraping other platforms they more likely would not require a change in code to do so. Future plans include scraping images from another source (most likely thecoverproject.net) in order to get better quality images than those on GameFAQs, that may be uploaded under a different project if completed._