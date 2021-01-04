Comment (module for Omeka S)
============================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Comment] is a module for [Omeka S] that allows users and/or public to comment
resources. It includes both ReCaptchas and Akismet spam detection if needed.
Comments can be flagged and moderated.

Comment can be displayed in public view or not, allowing librarian to comment
the resource themselves, publicly or privately.

Note: Akismet requires a dependency that is not installed automatically.

The aim of this module is to provide the same type of features than the [Omeka Classic]
[Commenting plugin]. It can be upgraded automatically via the plugin [Upgrade To Omeka S].


Installation
------------

First, install the two optional modules [Generic] and [Blocks Disposition].

Uncompress files and rename plugin folder `Comment`.

See general end user documentation for [Installing a module] and follow the
config instructions.


Requirements
------------

The comment module makes use of both ReCaptchas and the Akismet spam-detection
service. You will want to get API keys to both of these services and add them to
Omeka S main configuration for ReCaptchas key on inside the main settings page.

If not enabled, a simple anti-spam is available too.


Displaying Comments
-------------------

The comments are displayed automatically on item set, item or media pages. To
manage the display more precisely, use the module [Blocks Disposition], or add
the following code in your theme:

```php
<!-- Via a standard partial. -->
<?php echo $this->partial('common/comment', ['resource' => $resource]); ?>

<!-- Or via the helpers. -->
<div id="comments-container">
    <?php echo $this->showComments($resource); ?>
    <?php echo $this->showCommentForm($resource); ?>
</div>
```

The structure of comments, the comment itself, and the comment form can be
themed.

In the admin board, the comments are available in the details of the browse
pages and in the show pages of each resource. They can be filtered and managed
in the main comment page.


Use Cases
---------

### Limited, moderated commenting

An institution wants only trusted people to leave comments for anyone to read.
It doesn’t trust some of them enough to allow comments to be automatically
public.

The comments can be moderated by the global admin, the site admin, the editor or
the reviewer.

### Open commenting, with registered users getting to submit comments without approval

Install and configure the [Guest] module. Set commenting to Public so that
anyone can comment.

### Closed commenting for resources management

It’s possible to comment resources internally, for example to improve the
quality of metadata, or in a discussion between an author and a reviewer.


TODO
----

- [ ] Move some parameters from main settings to site settings.
- [ ] Convert comment into annotations (module Annotate).
- [ ] Manage comments with module Guest.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Daniel Berthereau, 2017-2021 (see [Daniel-KM] on GitLab)


[Comment]: https://gitlab.com/Daniel-KM/Omeka-S-module-Comment
[Omeka S]: https://omeka.org/s
[Omeka Classic]: https://omeka.org/classic
[Commenting plugin]: https://omeka.org/classic/plugins/Commenting
[Upgrade To Omeka S]: https://gitlab.com/Daniel-KM/Omeka-S-module-UpgradeToOmekaS
[Generic]: https://gitlab.com/Daniel-KM/Omeka-S-module-Generic
[Blocks Disposition]: https://gitlab.com/Daniel-KM/Omeka-S-module-BlocksDisposition
[Installing a module]: http://dev.omeka.org/docs/s/user-manual/modules/#installing-modules
[Guest]: https://gitlab.com/Daniel-KM/Omeka-S-module-Guest
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Comment/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
