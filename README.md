# Philosophy

Over the last decade I've encountered some principles that work as design guidelines as I develop, so I thought I'd share.

1. Dependencies will eventually break something (avoid them as much as possible)
2. When controlling flow, try to avoid conditions (try to avoid them generally) in favor of loops and other data-structured implementations and null-coalescing syntax [1]
3. A well designed application does not need to be heavily configured, rather, just adhere to patterns set forth. Configuration should track arbitrary named things: ie, if it seems like it would never change for any reason, then it is not a configuration.
4. The most complex parts of a program—and I don't think avoiding complexity is particularly realistic goal—should be done in an agnostic format, like sql, xpath, or regular expressions. 

[1] examles

I think conditions should only deal with problems where you don't know much about the data in question and you need to *compare* them (numbers are a good example like dates, ratios, sizes, etc.). Contrasted to when you actually know what you are looking for, using logic to pick through options doesn't look too saavy, and I try to avoid it because I personally find those types of expressions prone to oversight and hard to revise when needed.

```
// if/else
if (!isset($data['key])) return null;
else return $data['key'];

// better
return $data['key'] ?? null;

// if/else
if (!is_null($option)) {
  if ($option == 'A') {
    $pick = 'keywordA';
  } else if ($option == 'B') {
    $pick = 'keywordB';
  } else {
    $pick = 'keywordDefault';
  }
}

// better (also note, if the array comes from elsewhere, this is portable without adding another function to do a simple thing)
$pick = ['A' => 'keywordA', 'B' => 'keywordB'][$option] ?? 'keywordDefalut';
```

# Usage

Routing is done with callbacks. For more complicated methods, a controller can be specified as a callback. Some guidance on when/how to use controllers:

- A good design technique to employ; methods in a controller should be prepared share a base template (forms, listings, cards) and customize that as necessary.
- The anonymous class should inherit from the abstract `controller` class somewhere in the foodchain



## Templating

One of the more thorough features is the nature of templating. 