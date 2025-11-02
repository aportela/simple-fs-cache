<?php

namespace aportela\SimpleFSCache;

enum CacheFormat: string
{
    case NONE = "";
    case JSON = "json";
    case XML = "xml";
    case TXT = "txt";
    case HTML = "html";
    case PNG = "png";
    case JPG = "jpg";
}
