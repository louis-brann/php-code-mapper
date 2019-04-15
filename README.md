
# PHP Code Mapper

### Purpose

A tool to map out the `require_once` dependencies that a file or set of file
actually needs.

### Motivation

I was working on a PHP project that used a lot of `require_once`s to 
manage its dependencies between files. As the project grew, some files 
referred to files that no longer existed, or referred to files because
of certain functions or classes that had been moved to different files.

In short, `require_once` dependencies can get out of date.

Does it matter if they still work?

Well, kind of. There was a actually a fun bug caused by the interaction of
closing tags `?>`, implicit `require`s, and printing responses to the client.
Clients started crashing. Upon examining the responses, there were extra
newlines. Where were the newlines coming from? It turns out that there were some
old files that used closing tags with extra new lines after them, that had
recently been getting implicitly required from somewhere the require tree.

I confirmed this was the problem by putting an output buffer around the 
`require` statements. So the immediate solution was to delete all closing tags
from (PHP-only scripts in) the project, but that was somewhat unsatisfying.
Which files were actually being `require`d? None of the immediate dependencies
had closing tags. But each one did have its own list of required files... oh
lord. To figure it out, I didn't really have a tool for it, so I manually binary
searched using the output buffer approach and eventually found the culprit(s).
But that was 1. annoyingly tedious and 2. unsustainable for the future.

So what I'd ideally like to see as a result is
1. No implicit `require`s
2. To that end, something that can map out what dependencies each file
   actually needs

I had looked into other code mapping tools, to see if I would be able to
visualize what our project's `require` tree looked like, but I didn't find
anything that fit what I wanted. Plus it's a cool problem so why not do it
myself anyway.

And so that's the purpose of this tool. The meta-purpose is to teach myself
how to make a quality public facing project. So there will be a lot of
bells and whistles to come. This is just the start