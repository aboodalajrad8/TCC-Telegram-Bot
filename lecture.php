<?php

/**
 * ========================================================================
 * LECTURE STORAGE TELEGRAM BOT
 * ========================================================================
 * A fully-featured Telegram bot for organizing, storing, and distributing
 * academic lecture materials. Built with a hierarchical folder/file system,
 * role-based access control, and secure file hosting via a dedicated
 * Telegram storage channel.
 *
 * ========================================================================
 * LICENSE & COPYRIGHT
 * ========================================================================
 * MIT License
 *
 * Copyright (c) 2026 Abdullah Al-Ajrad
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * ========================================================================
 * DEVELOPER INFORMATION
 * ========================================================================
 * Author  : Abdullah Al-Ajrad
 * Email   : abodabdu799@gmail.com
 * GitHub  : https://github.com/aboodalajrad8
 * Version : 1.0.0
 * License : MIT
 *
 * ========================================================================
 * ARCHITECTURE OVERVIEW
 * ========================================================================
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │                     TELEGRAM WEBHOOK                        │
 * │              (php://input  JSON  $update)                 │
 * └──────────────────────┬──────────────────────────────────────┘
 *                        │
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │                    REQUEST DISPATCHER                        │
 * │  ┌─────────────┐  ┌──────────────┐  ┌──────────────────┐   │
 * │  │ Text Input  │  │ Callback     │  │ File Upload      │   │
 * │  │ (/$text)    │  │ Query ($data)│  │ ($document, etc) │   │
 * │  └──────┬──────┘  └──────┬───────┘  └────────┬─────────┘   │
 * └─────────┼────────────────┼───────────────────┼──────────────┘
 *           │                │                   │
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │                   ROUTING LAYER                              │
 * │  ┌──────────────────────────────────────────────────────┐   │
 * │  │  Pattern Matching via preg_match() & string compare  │   │
 * │  │  Callback prefixes: l_open_, l_admin_, l_owner_...   │   │
 * │  └──────────────────────────────────────────────────────┘   │
 * └──────────────────────┬──────────────────────────────────────┘
 *                        │
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │                    DATA LAYER                                │
 * │  ┌──────────────┐  ┌───────────────┐  ┌────────────────┐   │
 * │  │ lecture_     │  │ lecture_      │  │ lecture_       │   │
 * │  │ buttons.json │  │ settings.json │  │ admin.json     │   │
 * │  │ (hierarchical│  │ (roles, conf) │  │ (sessions)     │   │
 * │  │  button tree)│  │               │  │                │   │
 * │  └──────────────┘  └───────────────┘  └────────────────┘   │
 * └──────────────────────┬──────────────────────────────────────┘
 *                        │
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │                    STORAGE CHANNEL                           │
 * │   All lecture files are forwarded to a dedicated Telegram   │
 * │   channel for permanent, secure hosting. The bot stores    │
 * │   only the file_id pointers in buttons.json.               │
 * └─────────────────────────────────────────────────────────────┘
 *
 * ========================================================================
 * ROLE HIERARCHY
 * ========================================================================
 *
 *    OWNER (Hardcoded ID)
 *   ├── Manages admin list (add/remove)
 *   ├── Sets the storage channel
 *   └── Full access to all features
 *       │
 *
 *    ADMINS (Stored in settings.json)
 *   ├── Create/Edit/Delete folders & files
 *   ├── Upload lecture materials
 *   └── Full content management
 *       │
 *
 *    STUDENTS (Regular users)
 *   ├── Browse folder hierarchy
 *   ├── Download lecture files
 *   └── No management privileges
 *
 * ========================================================================
 * JSON DATA STRUCTURES
 * ========================================================================
 *
 * lecture_buttons.json:
 *   {
 *     "buttons": {
 *       "<random_id_8>": {
 *         "name": "Chapter 1",
 *         "type": "folder",
 *         "children": {
 *           "<random_id_8>": {
 *             "name": "Lecture 1.1 - Algebra",
 *             "type": "file",
 *             "file_id": "BQACAgQAAxk...",
 *             "mime": "application/pdf",
 *             "caption": "Optional description"
 *           }
 *         },
 *         "caption": null
 *       }
 *     }
 *   }
 *
 * lecture_settings.json:
 *   {
 *     "admins": ["123456789", "987654321"],
 *     "storage_channel": -1001234567890,
 *     "owner_id": "Owner Id"
 *   }
 *
 * lecture_admin.json:
 *   {
 *     "mode": { "<chat_id>": "l_wait_folder_name" },
 *     "nav":   { "<chat_id>": "root" },
 *     "temp":  { "<chat_id>": { "file_name": "..." } }
 *   }
 *
 * ========================================================================
 * CALLBACK DATA ROUTING TABLE
 * ========================================================================
 *
 * ┌────────────────────┬──────────────────────────────────────────────┐
 * │ PREFIX             │ PURPOSE                                      │
 * ├────────────────────┼──────────────────────────────────────────────┤
 * │ l_open_            │ Student opens a folder                       │
 * │ l_back             │ Student navigates back one level             │
 * │ l_download_        │ Student requests a file download             │
 * │ l_show             │ Student views the lecture listing            │
 * │ l_admin_panel      │ Opens the admin management panel             │
 * │ l_admin_open_      │ Admin opens a folder (with edit controls)    │
 * │ l_admin_back       │ Admin navigates back one level               │
 * │ l_admin_add        │ Admin chooses to add folder or file          │
 * │ l_admin_add_folder │ Admin confirms creating a folder             │
 * │ l_admin_add_file   │ Admin confirms creating a file button        │
 * │ l_admin_edit_      │ Admin selects a button to edit               │
 * │ l_admin_edit_name  │ Admin chooses to rename a button             │
 * │ l_admin_edit_cap.. │ Admin chooses to change the caption          │
 * │ l_admin_delete_    │ Admin deletes a button                       │
 * │ l_owner_panel      │ Opens the owner configuration dashboard      │
 * │ l_owner_add_admin  │ Owner adds a new administrator               │
 * │ l_owner_remove_    │ Owner removes an existing administrator      │
 * │ l_owner_set_storage│ Owner configures the storage channel         │
 * └────────────────────┴──────────────────────────────────────────────┘
 */


/**
 * ========================================================================
 * SECTION 1: BOT CONFIGURATION & CONSTANTS
 * ========================================================================
 * Core bot token and system-wide constant definitions.
 * These values are immutable during runtime.
 * ========================================================================
 */


/**
 * The Telegram Bot API token obtained from @BotFather.
 * This token authenticates all API requests made by this bot.
 *
 * @var string
 */
$token = "Enter Your Telegram Token here";

/**
 * Define the API key as a global constant for universal access.
 * Used throughout the system for all Telegram API communications.
 */
define("API_KEY", $token);

/**
 * The Telegram User ID of the bot owner (super administrator).
 * This ID is hardcoded to ensure the owner always retains
 * full control over the bot, even if all admins are removed.
 * The owner has exclusive access to:
 *   - Managing the administrator list
 *   - Configuring the storage channel
 *   - All administrative capabilities
 *
 * @var int
 */
define("OWNER_ID", "Owner Id");


/**
 * ========================================================================
 * SECTION 2: CORE API FUNCTIONS
 * ========================================================================
 * These functions serve as the foundational communication layer
 * between the bot and the Telegram Bot API. Every interaction with
 * Telegram's servers flows through these utilities.
 * ========================================================================
 */


/**
 * Execute a Telegram Bot API method with the given parameters.
 *
 * This is the primary HTTP client function. It constructs a properly
 * formatted URL, sends a GET request via file_get_contents(), and
 * returns the decoded JSON response as a PHP object.
 *
 * All other messaging functions ultimately delegate to this function.
 *
 * @param string $method The Telegram API method name (e.g., "sendMessage",
 *                       "editMessageText", "sendDocument", "getChat").
 * @param array  $datas  An associative array of parameters to pass to
 *                       the API method (e.g., chat_id, text, reply_markup).
 *
 * @return object|null The decoded JSON response from Telegram's servers.
 *                     Returns null if the request fails or returns invalid JSON.
 *
 * Usage Example:
 *   bot("sendMessage", [
 *       "chat_id" => 123456,
 *       "text"    => "Hello, World!"
 *   ]);
 */
function bot($method, $datas = [])
{
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $datas = http_build_query($datas);
    $res = file_get_contents($url . "?" . $datas);
    return json_decode($res);
}


/**
 * ========================================================================
 * SECTION 3: INCOMING DATA EXTRACTION
 * ========================================================================
 * Parse the raw JSON webhook payload from Telegram into structured
 * PHP variables. This section handles both standard message updates
 * and callback query updates (inline button presses).
 *
 * All variables are prefixed with '@' to suppress PHP warnings when
 * certain fields are absent (e.g., a text message has no document).
 * ========================================================================
 */


/**
 * Decode the raw JSON input from Telegram's webhook into a PHP object.
 * This is the entry point for all incoming bot interactions.
 *
 * @var object|null $update The complete update object from Telegram.
 */
@$update = json_decode(file_get_contents("php://input"));

/**
 * Extract the message object from the update.
 * Present when a user sends a text message, file, or any content.
 *
 * @var object|null $message
 */
@$message = $update->message;

/**
 * The sender's unique Telegram user ID.
 * Used for user identification and permission checking.
 *
 * @var int|null $id
 */
@$id = $message->from->id;

/**
 * The chat (conversation) ID where the message was sent.
 * This could be a private chat, group, or channel.
 *
 * @var int|null $chat_id
 */
@$chat_id = $message->chat->id;

/**
 * The text content of the user's message.
 * Only populated for text-based messages.
 *
 * @var string|null $text
 */
@$text = $message->text;

/**
 * The sender's first name, used for display purposes.
 *
 * @var string|null $user1
 */
@$user1 = $message->from->first_name;

/**
 * The sender's Telegram username (without the @ symbol).
 * May be null if the user has not set a username.
 *
 * @var string|null $user
 */
@$user = $message->from->username;

/**
 * Contains information about the original chat when a message
 * has been forwarded. Crucial for the storage channel setup feature.
 *
 * @var object|null $forward_from_chat
 */
@$forward_from_chat = $message->forward_from_chat;

/**
 * Contains forward origin information for recent Telegram clients.
 * Provides an alternative way to detect forwarded messages.
 *
 * @var object|null $forward_origin
 */
@$forward_origin = $message->forward_origin;

/**
 * Document (file) attachment data. Present when a user sends a file.
 * Contains file_id, file_name, mime_type, and file_size.
 *
 * @var object|null $document
 */
@$document = $message->document;

/**
 * Video attachment data. Present when a user sends a video file.
 *
 * @var object|null $video
 */
@$video = $message->video;

/**
 * Audio attachment data. Present when a user sends an audio file.
 *
 * @var object|null $audio
 */
@$audio = $message->audio;

/**
 * Photo attachment data. Telegram sends photos as an array of
 * progressively larger sizes. The last element (highest resolution)
 * is typically used for storage.
 *
 * @var array|null $photo
 */
@$photo = $message->photo;

/**
 * Handle Callback Query Updates
 * ------------------------------------------------------------------------
 * When a user clicks an inline keyboard button, Telegram sends a
 * callback query instead of a regular message. This block extracts
 * the relevant data and overrides the standard message variables
 * to unify downstream handling.
 *
 * Key differences from text messages:
 *   - $data contains the callback_data string instead of $text
 *   - $message_id refers to the original message that has the keyboard
 *   - chat_id is extracted from the callback_query context
 */
if (isset($update->callback_query)) {
    $chat_id = $update->callback_query->message->chat->id;
    $message_id = $update->callback_query->message->message_id;
    $data = $update->callback_query->data;
    $user = $update->callback_query->from->username;
    $user2 = $update->callback_query->from->first_name;
    $callback_id = $update->callback_query->id;
}


/**
 * ========================================================================
 * SECTION 4: INITIAL DATA LOADING
 * ========================================================================
 * Load all persistent data from JSON files at the start of each
 * request cycle. This ensures that every handler has access to
 * the current state of the system.
 * ========================================================================
 */


/**
 * Load the admin session state containing mode, navigation, and temp data.
 * This file tracks what each admin user is currently doing.
 *
 * @var array|null $lecture
 */
@$lecture = json_decode(file_get_contents("storage/lecture_admin.json"), 1);

/**
 * Load the bot configuration containing admin list, storage channel, etc.
 *
 * @var array|null $settings
 */
@$settings = json_decode(file_get_contents("storage/lecture_settings.json"), 1);

/**
 * The list of authorized administrator Telegram user IDs.
 *
 * @var array $admins
 */
@$admins = $settings["admins"];

/**
 * The Telegram chat ID of the dedicated file storage channel.
 * All lecture files are forwarded here for permanent hosting.
 *
 * @var int|null $storage_channel
 */
@$storage_channel = $settings["storage_channel"];

/**
 * The current operational mode for this user's session.
 * Controls which handler will process the next user input.
 * Examples: "l_wait_folder_name", "l_wait_file_upload", etc.
 *
 * @var string|null $mode
 */
@$mode = $lecture["mode"][$chat_id];

/**
 * The current navigation location within the folder hierarchy.
 * "root" indicates the top-level directory. Any other value
 * is a button ID representing the currently open folder.
 *
 * @var string|null $nav
 */
@$nav = $lecture["nav"][$chat_id];


/**
 * ========================================================================
 * SECTION 5: MESSAGING UTILITY FUNCTIONS
 * ========================================================================
 * High-level wrappers around the Telegram Bot API for sending
 * and editing messages with inline keyboard support.
 * ========================================================================
 */


/**
 * Send a new message to a specified chat with an optional inline keyboard.
 *
 * This function wraps the Telegram sendMessage API method and
 * automatically serializes the reply_markup array into JSON format.
 *
 * @param int        $chat_id      The target chat's unique identifier.
 * @param string     $text         The message text content. Supports HTML
 *                                 and Markdown formatting if enabled.
 * @param array      $reply_markup An array of inline keyboard button rows.
 *                                 Each row is an array of button objects.
 *                                 Example:
 *                                 [
 *                                     [["text" => "Button 1", "callback_data" => "data1"]],
 *                                     [["text" => "Button 2", "callback_data" => "data2"]]
 *                                 ]
 *
 * @return object|null The Telegram API response object.
 */
function sendmsg($chat_id, $text, $reply_markup = [])
{
    $abod = bot("sendmessage", [
        "chat_id" => $chat_id,
        "text" => $text,
        "reply_markup" => json_encode([
            "inline_keyboard" => $reply_markup
        ])
    ]);
    return $abod;
}

/**
 * Edit the text and keyboard of an existing message.
 *
 * Used extensively throughout the bot to provide seamless navigation
 * without cluttering the chat history. Instead of sending new messages,
 * the bot modifies the current message in place.
 *
 * @param int        $chat_id      The chat containing the target message.
 * @param int        $message_id   The message ID to edit.
 * @param string     $text         The new text content for the message.
 * @param array      $reply_markup The new inline keyboard layout (optional).
 *
 * @return object|null The Telegram API response object.
 */
function editmsg($chat_id, $message_id, $text, $reply_markup = [])
{
    $abod = bot("editmessagetext", [
        "chat_id" => $chat_id,
        "message_id" => $message_id,
        "text" => $text,
        "reply_markup" => json_encode([
            "inline_keyboard" => $reply_markup
        ])
    ]);
    return $abod;
}

/**
 * Store a file into the storage channel using the correct Telegram method.
 * Using sendDocument for a photo/video/audio file_id causes
 * "wrong file identifier" errors, which is why photo uploads failed.
 *
 * @param int|string $storage_channel Target channel ID.
 * @param string $file_id Original file_id from admin upload.
 * @param string $file_type One of: document, video, audio, photo.
 * @param string $caption Caption for storage copy.
 * @return string|false New file_id on success, false on failure.
 */
function storeLectureFile($storage_channel, $file_id, $file_type, $caption)
{
    if ($file_type === "photo") {
        $res = bot("sendPhoto", [
            "chat_id" => $storage_channel,
            "photo" => $file_id,
            "caption" => $caption,
        ]);
        if ($res && isset($res->ok) && $res->ok && isset($res->result->photo)) {
            $photos = $res->result->photo;
            $last = $photos[count($photos) - 1];
            return $last->file_id;
        }
        return false;
    } elseif ($file_type === "video") {
        $res = bot("sendVideo", [
            "chat_id" => $storage_channel,
            "video" => $file_id,
            "caption" => $caption,
        ]);
        if ($res && isset($res->ok) && $res->ok) {
            if (isset($res->result->video->file_id)) {
                return $res->result->video->file_id;
            }
            if (isset($res->result->document->file_id)) {
                return $res->result->document->file_id;
            }
        }
        return false;
    } elseif ($file_type === "audio") {
        $res = bot("sendAudio", [
            "chat_id" => $storage_channel,
            "audio" => $file_id,
            "caption" => $caption,
        ]);
        if ($res && isset($res->ok) && $res->ok) {
            if (isset($res->result->audio->file_id)) {
                return $res->result->audio->file_id;
            }
            if (isset($res->result->document->file_id)) {
                return $res->result->document->file_id;
            }
        }
        return false;
    } else {
        $res = bot("sendDocument", [
            "chat_id" => $storage_channel,
            "document" => $file_id,
            "caption" => $caption,
        ]);
        if ($res && isset($res->ok) && $res->ok && isset($res->result->document->file_id)) {
            return $res->result->document->file_id;
        }
        return false;
    }
}

/**
 * Send a stored file to a user using the correct Telegram method.
 * Photo file_ids must go via sendPhoto, otherwise Telegram returns
 * "wrong file identifier" when sent as document.
 */
function sendLectureFile($chat_id, $file_id, $mime, $caption)
{
    $mime = (string)$mime;
    if ($mime === "photo") {
        return bot("sendPhoto", [
            "chat_id" => $chat_id,
            "photo" => $file_id,
            "caption" => $caption,
        ]);
    } elseif ($mime === "video" || strpos($mime, "video/") === 0) {
        return bot("sendVideo", [
            "chat_id" => $chat_id,
            "video" => $file_id,
            "caption" => $caption,
        ]);
    } elseif ($mime === "audio" || strpos($mime, "audio/") === 0) {
        return bot("sendAudio", [
            "chat_id" => $chat_id,
            "audio" => $file_id,
            "caption" => $caption,
        ]);
    } else {
        return bot("sendDocument", [
            "chat_id" => $chat_id,
            "document" => $file_id,
            "caption" => $caption,
        ]);
    }
}


/**
 * ========================================================================
 * SECTION 6: PERSISTENCE UTILITY FUNCTIONS
 * ========================================================================
 * Functions for reading and writing JSON data files in the storage
 * directory. These form the backbone of the bot's data persistence.
 * ========================================================================
 */


/**
 * Save an associative array to a JSON file in the storage directory.
 *
 * This function intelligently handles filenames: if the provided
 * filename already ends with ".json", it uses it as-is; otherwise,
 * it appends ".json" automatically. All files are formatted with
 * pretty-print for human readability.
 *
 * @param string $file  The filename (with or without .json extension).
 * @param array  $array The data structure to persist.
 *
 * @return void
 *
 * Usage Examples:
 *   add("lecture_settings", $settings);     //  storage/lecture_settings.json
 *   add("config.json", $config);            //  storage/config.json
 */
function add($file, $array): void
{
    if (strpos(haystack: $file, needle: ".json") !== false) {
        file_put_contents(filename: "storage/" . $file, data: json_encode(value: $array, flags: JSON_PRETTY_PRINT));
    } else {
        file_put_contents(filename: "storage/" . $file . ".json", data: json_encode(value: $array, flags: JSON_PRETTY_PRINT));
    }
}

/**
 * Persist the admin session state array to its dedicated JSON file.
 *
 * This is a convenience wrapper specifically for the admin session
 * data. It is called frequently throughout the bot to save the
 * current mode, navigation state, and temporary data after every
 * user interaction that modifies the session.
 *
 * @param array $array The complete admin session data structure.
 *
 * @return void
 */
function saveLectureAdmin($array): void
{
    file_put_contents(filename: "storage/lecture_admin.json", data: json_encode(value: $array, flags: JSON_PRETTY_PRINT));
}


/**
 * ========================================================================
 * SECTION 7: FIRST-RUN INITIALIZATION
 * ========================================================================
 * On the very first execution, create the storage directory and all
 * required JSON files with their default structures. This ensures
 * the bot operates correctly immediately after deployment.
 * ========================================================================
 */


/**
 * Create the storage directory if it does not exist.
 * All persistent data files reside within this directory.
 */
if (!file_exists("storage")) {
    mkdir("storage");
}

/**
 * Create and initialize the settings file if absent.
 * The default configuration includes:
 *   - An empty admin list
 *   - No storage channel (must be configured by the owner)
 *   - The hardcoded owner ID for access control
 */
if (!file_exists("storage/lecture_settings.json")) {
    $settings = [];
    $settings["admins"] = [];
    $settings["storage_channel"] = null;
    $settings["owner_id"] = OWNER_ID;
    add("lecture_settings", $settings);
}

/**
 * Create and initialize the admin session file if absent.
 * This file maintains per-user state across webhook requests:
 *   - mode: The current action being performed by each user
 *   - nav:  The current navigation location in the folder tree
 *   - temp: Temporary data (file names, edit targets, etc.)
 */
if (!file_exists("storage/lecture_admin.json")) {
    $lecture = [];
    $lecture["mode"] = [];
    $lecture["nav"] = [];
    $lecture["temp"] = [];
    $lecture["flood"] = [];
    saveLectureAdmin($lecture);
}

/**
 * Create and initialize the buttons database file if absent.
 * This file stores the complete hierarchical tree of folders and files.
 * It starts empty, allowing administrators to build the structure.
 */
if (!file_exists("storage/lecture_buttons.json")) {
    add("lecture_buttons", ["buttons" => []]);
}


/**
 * ========================================================================
 * SECTION 8: POST-INITIALIZATION DATA RELOAD
 * ========================================================================
 * After initialization (which may have created the files), reload all
 * data into local variables to ensure the current request has access
 * to the latest persisted state.
 * ========================================================================
 */


/**
 * Reload all data files after potential initialization.
 * This guarantees that newly created files are properly read,
 * and any default values are available for the request cycle.
 */
$lecture = json_decode(file_get_contents("storage/lecture_admin.json"), 1);
$settings = json_decode(file_get_contents("storage/lecture_settings.json"), 1);
$buttonsData = json_decode(file_get_contents("storage/lecture_buttons.json"), 1);
$admins = $settings["admins"];
$storage_channel = $settings["storage_channel"];
$mode = $lecture["mode"][$chat_id];
$nav = $lecture["nav"][$chat_id];


/**
 * ========================================================================
 * SECTION 9: AUTHORIZATION FUNCTIONS
 * ========================================================================
 * Permission-checking utilities that determine what actions a user
 * is allowed to perform. These functions are consulted at the
 * beginning of protected operations.
 * ========================================================================
 */


/**
 * Check if the given user ID belongs to the bot owner.
 *
 * The owner is identified by the hardcoded OWNER_ID constant and has
 * unrestricted access to all bot features, including administrative
 * management and system configuration.
 *
 * @param int $chat_id The Telegram user ID to check.
 *
 * @return bool True if the user is the owner, false otherwise.
 */
function isOwner($chat_id): bool
{
    return $chat_id == OWNER_ID;
}

/**
 * Check if the given user ID has administrative privileges.
 *
 * A user is considered an admin if they are either:
 *   1. The bot owner (highest privilege level), OR
 *   2. Listed in the admins array from settings.json
 *
 * This function is the primary gatekeeper for all management features.
 *
 * @param int $chat_id The Telegram user ID to check.
 *
 * @return bool True if the user is an owner or admin, false otherwise.
 */
function isAdmin($chat_id): bool
{
    global $admins;
    return in_array($chat_id, $admins) || isOwner($chat_id);
}


/**
 * ========================================================================
 * SECTION 9B: ANTI-SPAM RATE LIMIT (5 SECONDS)
 * ========================================================================
 * Prevents users from spamming buttons/files. If a non-admin sends
 * a new request within 5 seconds of the last allowed one:
 *   - callback clicks -> answerCallbackQuery (popup, no new message)
 *   - text messages   -> sendmsg warning
 * Admins are exempt so multi-file uploads and fast management still work.
 * ========================================================================
 */
define("LECTURE_FLOOD_SECONDS", 5);

// Migration for existing installs: ensure flood array exists.
if (!isset($lecture["flood"]) || !is_array($lecture["flood"])) {
    $lecture["flood"] = [];
    saveLectureAdmin($lecture);
}

if (!empty($chat_id) && !isAdmin($chat_id)) {
    $now = time();
    $last = isset($lecture["flood"][$chat_id]) ? (int)$lecture["flood"][$chat_id] : 0;
    $elapsed = $now - $last;
    if ($last !== 0 && $elapsed < LECTURE_FLOOD_SECONDS) {
        $wait = LECTURE_FLOOD_SECONDS - $elapsed;
        if (isset($update->callback_query) && isset($callback_id)) {
            bot("answerCallbackQuery", [
                "callback_query_id" => $callback_id,
                "text" => " انتظر $wait ثواني قبل المحاولة مرة أخرى",
                "show_alert" => false,
            ]);
        } elseif (isset($text)) {
            sendmsg($chat_id, " تمهل قليلاً، انتظر $wait ثواني قبل إرسال طلب جديد.");
        }
        exit(0);
    }
    // Allowed: record timestamp for next check.
    $lecture["flood"][$chat_id] = $now;
    saveLectureAdmin($lecture);
}


/**
 * ========================================================================
 * SECTION 10: FOLDER/NAVIGATION UTILITY FUNCTIONS
 * ========================================================================
 * Functions for traversing and rendering the hierarchical button tree.
 * These utilities handle the recursive folder structure and generate
 * the appropriate inline keyboard layouts for both admin and student views.
 * ========================================================================
 */


/**
 * Retrieve the set of buttons visible at a given navigation location.
 *
 * This function resolves the current folder context. If $nav is null
 * or "root", it returns the top-level buttons. If $nav points to a
 * valid folder, it returns that folder's children array.
 *
 * @param string|null $nav The navigation key (button ID) or null/root.
 *
 * @return array An array containing:
 *               [0] => The array of buttons at this level,
 *               [1] => The parent key ("root" or the parent folder ID).
 */
function getCurrentButtons($nav = null)
{
    global $buttonsData;
    $buttons = $buttonsData["buttons"];
    if ($nav === null || $nav === "root" || !isset($buttons[$nav])) {
        $parent = "root";
        return [$buttons, $parent];
    } else {
        $folder = $buttons[$nav];
        if ($folder["type"] !== "folder") {
            $parent = "root";
            return [$buttons, $parent];
        }
        $parent = $nav;
        return [$folder["children"], $parent];
    }
}

/**
 * Generate an inline keyboard markup array for displaying buttons.
 *
 * This is the core rendering function that transforms the button
 * data structure into a Telegram-compatible inline keyboard array.
 * It supports two view modes:
 *
 * Admin View:
 *   - Shows edit () and delete () controls next to each button
 *   - Includes an "Add Button" option
 *   - Shows navigation controls (back, main menu)
 *
 * Student View:
 *   - Shows folders with a folder icon () and open action
 *   - Shows files with a file icon () and download action
 *   - Shows a back button when inside a subfolder
 *
 * @param array       $currentButtons The array of buttons to render.
 * @param string|null $nav            The current navigation context.
 * @param bool        $isAdminView    Whether to render admin controls.
 *
 * @return array The formatted inline keyboard array.
 */
function renderFolderButtons($currentButtons, $nav, $isAdminView = false)
{
    $keyboard = [];
    foreach ($currentButtons as $key => $button) {
        if ($button["type"] === "folder") {
            if ($isAdminView) {
                $keyboard[] = [
                    ["text" => " " . $button["name"], "callback_data" => "l_admin_open_" . $key],
                    ["text" => "", "callback_data" => "l_admin_edit_" . $key],
                    ["text" => "", "callback_data" => "l_admin_delete_" . $key],
                ];
            } else {
                $keyboard[] = [
                    ["text" => " " . $button["name"], "callback_data" => "l_open_" . $key],
                ];
            }
        } else {
            if ($isAdminView) {
                $keyboard[] = [
                    ["text" => " " . $button["name"], "callback_data" => "l_admin_edit_" . $key],
                    ["text" => "", "callback_data" => "l_admin_delete_" . $key],
                ];
            } else {
                $keyboard[] = [
                    ["text" => " " . $button["name"], "callback_data" => "l_download_" . $key],
                ];
            }
        }
    }
    if ($isAdminView) {
        $keyboard[] = [
            ["text" => " اضافة زر", "callback_data" => "l_admin_add"],
        ];
        if ($nav !== null && $nav !== "root") {
            $keyboard[] = [
                ["text" => " رجوع", "callback_data" => "l_admin_back"],
            ];
        }
        $keyboard[] = [
            ["text" => " القائمة الرئيسية", "callback_data" => "l_admin_panel"],
        ];
    } else {
        if ($nav !== null && $nav !== "root") {
            $keyboard[] = [
                ["text" => " رجوع", "callback_data" => "l_back"],
            ];
        }
    }
    return $keyboard;
}


/**
 * Recursively find a folder by its ID in the button tree and return a
 * reference to its children array.
 *
 * This enables admin operations (add, delete, edit) to work correctly
 * from ANY nesting depth, not just root-level or single-level nested
 * folders. Without this function, operations inside folders nested 2+
 * levels deep would silently corrupt the data structure by creating
 * orphaned root-level entries.
 *
 * @param array       &$buttons The full buttons array (or sub-tree).
 * @param string|null  $folderId The button ID of the folder to find.
 *
 * @return array|null Reference to the folder's children, or null if
 *                    the folder cannot be found (caller falls back
 *                    to root level).
 */
function &findFolderChildrenRef(&$buttons, $folderId)
{
    if ($folderId === null || $folderId === "root") {
        $null = null;
        return $null;
    }
    if (isset($buttons[$folderId]) && $buttons[$folderId]["type"] === "folder") {
        return $buttons[$folderId]["children"];
    }
    foreach ($buttons as $key => &$btn) {
        if ($btn["type"] === "folder" && !empty($btn["children"])) {
            $found = &findFolderChildrenRef($btn["children"], $folderId);
            if ($found !== null) {
                unset($btn);
                return $found;
            }
        }
        unset($btn);
    }
    $null = null;
    return $null;
}


/**
 * Recursively find the parent ID of a folder in the button tree.
 *
 * This enables back-navigation to work correctly from any nesting
 * depth. Without this, clicking "back" from 3+ levels deep would
 * return to the root instead of the actual parent folder.
 *
 * @param array       $buttons  The full buttons array (or sub-tree).
 * @param string|null $folderId The button ID whose parent to find.
 *
 * @return string|null The parent folder ID, or null if not found
 *                     (caller falls back to "root").
 */
function findParentId($buttons, $folderId)
{
    foreach ($buttons as $key => $btn) {
        if ($btn["type"] === "folder" && isset($btn["children"][$folderId])) {
            return $key;
        }
        if ($btn["type"] === "folder" && !empty($btn["children"])) {
            $found = findParentId($btn["children"], $folderId);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}


/**
 * ========================================================================
 * SECTION 11: COMMAND HANDLERS
 * ========================================================================
 * All user interaction handlers are organized below. Each handler
 * corresponds to a specific command, callback data pattern, or
 * user state. Handlers are checked sequentially and the first
 * match processes the request.
 *
 * NAVIGATION NOTE: The handler order matters. More specific patterns
 * must be checked before general ones. Each handler MUST call exit(0)
 * after processing to prevent fall-through to unintended handlers.
 * ========================================================================
 */


/**
 * ------------------------------------------------------------------------
 * HANDLER 11.1: /start Command
 * ------------------------------------------------------------------------
 * The entry point for all users. Routes users to different interfaces
 * based on their permission level:
 *
 * - Administrators: See the admin dashboard with management options
 * - Regular users:  See the top-level lecture folder structure
 *
 * This handler also resets the user's mode and navigation state,
 * providing a clean starting point for every session.
 * ------------------------------------------------------------------------
 */
if (!$data && isset($text) && $text === "/start") {
    if (isAdmin($chat_id)) {
        $msg = " مرحبا بك في نظام المحاضرات\n\nاختر من القائمة:";
        sendmsg($chat_id, $msg, [
            [
                ["text" => " ادارة المحاضرات", "callback_data" => "l_admin_panel"],
                ["text" => " عرض المحاضرات", "callback_data" => "l_show"],
            ],
            [
                ["text" => " لوحة المالك", "callback_data" => "l_owner_panel"],
            ],
        ]);
    } else {
        $msg = " مرحبا بك في نظام المحاضرات\n\n" . $devCredit;
        $currentButtons = $buttonsData["buttons"];
        $keyboard = renderFolderButtons($currentButtons, "root", false);
        sendmsg($chat_id, $msg, $keyboard);
    }
    $lecture["mode"][$chat_id] = null;
    $lecture["nav"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 11.2: Student View - Show All Lectures (l_show)
 * ------------------------------------------------------------------------
 * Displays the top-level folder/file structure to a regular user.
 * This is the student-friendly read-only view with no management controls.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_show") {
    $currentButtons = $buttonsData["buttons"];
    $keyboard = renderFolderButtons($currentButtons, "root", false);
    $msg = " المحاضرات المتاحة:";
    editmsg($chat_id, $message_id, $msg, $keyboard);
    $lecture["mode"][$chat_id] = null;
    $lecture["nav"][$chat_id] = "root";
    saveLectureAdmin($lecture);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 11.3: Student View - Open Folder (l_open_<id>)
 * ------------------------------------------------------------------------
 * Navigates into a folder from the student's perspective. Renders the
 * folder's children with student-friendly icons and download actions.
 *
 * Callback Pattern: l_open_<8-character-button-id>
 * ------------------------------------------------------------------------
 */
if (isset($data) && preg_match("/^l_open_/", $data)) {
    $replace = str_replace("l_open_", "", $data);
    $buttons = $buttonsData["buttons"];
    $folderFound = null;
    if (isset($buttons[$replace]) && $buttons[$replace]["type"] === "folder") {
        $folderFound = $buttons[$replace];
    } elseif (isset($lecture["nav"][$chat_id]) && $lecture["nav"][$chat_id] !== "root") {
        $currentNav = $lecture["nav"][$chat_id];
        $parentChildren = &findFolderChildrenRef($buttons, $currentNav);
        if ($parentChildren !== null && isset($parentChildren[$replace]) && $parentChildren[$replace]["type"] === "folder") {
            $folderFound = $parentChildren[$replace];
        }
    }
    if ($folderFound !== null) {
        $children = $folderFound["children"];
        $keyboard = renderFolderButtons($children, $replace, false);
        $msg = " " . $folderFound["name"];
        editmsg($chat_id, $message_id, $msg, $keyboard);
        $lecture["nav"][$chat_id] = $replace;
        saveLectureAdmin($lecture);
        exit(0);
    }
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 11.4: Student View - Navigate Back (l_back)
 * ------------------------------------------------------------------------
 * Moves the student up one level in the folder hierarchy. Determines
 * the parent folder by searching which folder contains the current nav
 * as a child. If at the root level, nothing changes.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_back") {
    $nav = $lecture["nav"][$chat_id];
    $buttons = $buttonsData["buttons"];
    $parent = "root";
    if ($nav !== null && $nav !== "root") {
        $foundParent = findParentId($buttons, $nav);
        if ($foundParent !== null) {
            $parent = $foundParent;
        }
    }
    if ($parent === "root") {
        $currentButtons = $buttons;
        $lecture["nav"][$chat_id] = "root";
    } else {
        $parentChildren = &findFolderChildrenRef($buttons, $parent);
        if ($parentChildren !== null) {
            $currentButtons = $parentChildren;
            $lecture["nav"][$chat_id] = $parent;
        } else {
            $currentButtons = $buttons;
            $lecture["nav"][$chat_id] = "root";
        }
    }
    saveLectureAdmin($lecture);
    $keyboard = renderFolderButtons($currentButtons, $lecture["nav"][$chat_id], false);
    $msg = " المحاضرات المتاحة:";
    editmsg($chat_id, $message_id, $msg, $keyboard);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 11.5: Student View - Download File (l_download_<id>)
 * ------------------------------------------------------------------------
 * Sends a lecture file to the user. The file is retrieved from the
 * storage channel using its saved file_id and sent as a document.
 *
 * The handler searches both the top-level buttons AND the children
 * of the currently open folder, allowing files at any depth to be
 * downloaded.
 *
 * Callback Pattern: l_download_<8-character-button-id>
 * ------------------------------------------------------------------------
 */
if (isset($data) && preg_match("/^l_download_/", $data)) {
    $replace = str_replace("l_download_", "", $data);
    $buttons = $buttonsData["buttons"];
    $found = false;
    $fileButton = null;
    if (isset($buttons[$replace]) && $buttons[$replace]["type"] === "file") {
        $fileButton = $buttons[$replace];
        $found = true;
    } else {
        $nav = $lecture["nav"][$chat_id];
        if ($nav !== null && $nav !== "root") {
            $parentChildren = &findFolderChildrenRef($buttons, $nav);
            if ($parentChildren !== null && isset($parentChildren[$replace]) && $parentChildren[$replace]["type"] === "file") {
                $fileButton = $parentChildren[$replace];
                $found = true;
            }
        }
    }
    if ($found && $fileButton !== null) {
        /**
         * Support both legacy single-file buttons (file_id) and the new
         * multi-file format (files array). If multiple files exist,
         * show a selection menu so the student can pick which file to download.
         */
        if (isset($fileButton["files"]) && count($fileButton["files"]) > 1) {
            /**
             * Multiple files: send ALL files directly one by one.
             * Each file is sent as a separate document to the user.
             */
            $fileCount = count($fileButton["files"]);
            for ($i = 0; $i < $fileCount; $i++) {
                $fileEntry = $fileButton["files"][$i];
                $fileCaption = isset($fileEntry["caption"]) ? $fileEntry["caption"] : $fileButton["name"] . " (" . ($i + 1) . "/" . $fileCount . ")";
                $fileMime = isset($fileEntry["mime"]) ? $fileEntry["mime"] : "document";
                sendLectureFile($chat_id, $fileEntry["file_id"], $fileMime, $fileCaption);
            }
            exit(0);
        } else {
            /**
             * Single file: send directly (works for both legacy file_id
             * and new format with 1 entry in the files array).
             */
            if (isset($fileButton["files"][0]["file_id"])) {
                $singleFileId = $fileButton["files"][0]["file_id"];
                $singleMime = isset($fileButton["files"][0]["mime"]) ? $fileButton["files"][0]["mime"] : "document";
            } elseif (isset($fileButton["file_id"])) {
                $singleFileId = $fileButton["file_id"];
                $singleMime = isset($fileButton["mime"]) ? $fileButton["mime"] : "document";
            } else {
                editmsg($chat_id, $message_id, "عذرا الملف غير متاح", [
                    [["text" => " رجوع", "callback_data" => "l_show"]],
                ]);
                exit(0);
            }
            $caption = isset($fileButton["caption"]) ? $fileButton["caption"] : $fileButton["name"];
            sendLectureFile($chat_id, $singleFileId, $singleMime, $caption);
            exit(0);
        }
    } else {
        editmsg($chat_id, $message_id, "عذرا الملف غير متاح", [
            [["text" => " رجوع", "callback_data" => "l_show"]],
        ]);
        exit(0);
    }
}


/**
 * ========================================================================
 * SECTION 12: ADMIN MANAGEMENT HANDLERS
 * ========================================================================
 * These handlers provide the administrative interface for managing
 * the lecture folder/file hierarchy. All handlers in this section
 * require the user to have admin or owner privileges.
 * ========================================================================
 */


/**
 * ------------------------------------------------------------------------
 * HANDLER 12.1: Admin Panel (l_admin_panel)
 * ------------------------------------------------------------------------
 * Opens the main administrative dashboard showing the root-level
 * button hierarchy with full management controls (edit, delete, add).
 * This is the central hub for all content management operations.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_admin_panel") {
    $currentButtons = $buttonsData["buttons"];
    $lecture["nav"][$chat_id] = "root";
    saveLectureAdmin($lecture);
    $keyboard = renderFolderButtons($currentButtons, "root", true);
    $msg = " لوحة ادارة المحاضرات";
    editmsg($chat_id, $message_id, $msg, $keyboard);
    $lecture["mode"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 12.2: Admin View - Open Folder (l_admin_open_<id>)
 * ------------------------------------------------------------------------
 * Navigates into a folder in the admin view. Unlike the student view,
 * this renders edit () and delete () controls alongside each button,
 * plus an "Add Button" option at the bottom.
 *
 * Callback Pattern: l_admin_open_<8-character-button-id>
 * ------------------------------------------------------------------------
 */
if (isset($data) && preg_match("/^l_admin_open_/", $data)) {
    $replace = str_replace("l_admin_open_", "", $data);
    $buttons = $buttonsData["buttons"];
    $folderFound = null;
    if (isset($buttons[$replace]) && $buttons[$replace]["type"] === "folder") {
        $folderFound = $buttons[$replace];
    } elseif (isset($lecture["nav"][$chat_id]) && $lecture["nav"][$chat_id] !== "root") {
        $currentNav = $lecture["nav"][$chat_id];
        $parentChildren = &findFolderChildrenRef($buttons, $currentNav);
        if ($parentChildren !== null && isset($parentChildren[$replace]) && $parentChildren[$replace]["type"] === "folder") {
            $folderFound = $parentChildren[$replace];
        }
    }
    if ($folderFound !== null) {
        $children = $folderFound["children"];
        $lecture["nav"][$chat_id] = $replace;
        saveLectureAdmin($lecture);
        $keyboard = renderFolderButtons($children, $replace, true);
        $msg = " " . $folderFound["name"];
        editmsg($chat_id, $message_id, $msg, $keyboard);
        exit(0);
    }
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 12.3: Admin View - Navigate Back (l_admin_back)
 * ------------------------------------------------------------------------
 * Moves the admin up one level in the folder hierarchy while preserving
 * the admin view (with management controls). Works identically to the
 * student back handler but uses admin-style rendering.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_admin_back") {
    $nav = $lecture["nav"][$chat_id];
    $buttons = $buttonsData["buttons"];
    $parent = "root";
    if ($nav !== null && $nav !== "root") {
        $foundParent = findParentId($buttons, $nav);
        if ($foundParent !== null) {
            $parent = $foundParent;
        }
    }
    if ($parent === "root") {
        $currentButtons = $buttons;
        $lecture["nav"][$chat_id] = "root";
    } else {
        $parentChildren = &findFolderChildrenRef($buttons, $parent);
        if ($parentChildren !== null) {
            $currentButtons = $parentChildren;
            $lecture["nav"][$chat_id] = $parent;
        } else {
            $currentButtons = $buttons;
            $lecture["nav"][$chat_id] = "root";
        }
    }
    saveLectureAdmin($lecture);
    $keyboard = renderFolderButtons($currentButtons, $lecture["nav"][$chat_id], true);
    $msg = " لوحة ادارة المحاضرات";
    editmsg($chat_id, $message_id, $msg, $keyboard);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 12.4: Admin - Choose Button Type (l_admin_add)
 * ------------------------------------------------------------------------
 * Prompts the admin to choose whether they want to create a new
 * folder or a new file button at the current navigation level.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_admin_add") {
    editmsg($chat_id, $message_id, "اختر نوع الزر:", [
        [
            ["text" => " مجلد", "callback_data" => "l_admin_add_folder"],
            ["text" => " ملف", "callback_data" => "l_admin_add_file"],
        ],
        [
            ["text" => " الغاء", "callback_data" => "l_admin_panel"],
        ],
    ]);
    $lecture["mode"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 12.5: Admin - Create New Folder (l_admin_add_folder)
 * ------------------------------------------------------------------------
 * Initiates the folder creation workflow. Sets the user's mode to
 * "l_wait_folder_name" and prompts them to enter the folder name.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_admin_add_folder") {
    editmsg($chat_id, $message_id, "ارسل اسم المجلد الجديد:", [
        [["text" => " الغاء", "callback_data" => "l_admin_panel"]],
    ]);
    $lecture["mode"][$chat_id] = "l_wait_folder_name";
    saveLectureAdmin($lecture);
    exit(0);
}

/**
 * ------------------------------------------------------------------------
 * HANDLER 12.6: Admin - Receive Folder Name (text handler)
 * ------------------------------------------------------------------------
 * Processes the folder name submitted by the admin. Creates a new
 * folder button with:
 *   - A unique 8-character random ID as the key
 *   - The user-provided name
 *   - Type set to "folder"
 *   - An empty children array for future nesting
 *
 * The new folder is added to either the root level or the currently
 * open folder, depending on the admin's navigation context.
 * ------------------------------------------------------------------------
 */
if (isset($text) && $mode === "l_wait_folder_name") {
    $buttons = $buttonsData["buttons"];
    $nav = $lecture["nav"][$chat_id];
    $rand = substr(str_shuffle("abcdefghijklmopqrstvwxyzABCDEFGHIJKLMNOPQRSTUVXYZ1234567890"), 0, 8);
    $newButton = [
        "name" => $text,
        "type" => "folder",
        "children" => [],
    ];
    if ($nav === null || $nav === "root") {
        $buttons[$rand] = $newButton;
    } else {
        $children = &findFolderChildrenRef($buttons, $nav);
        if ($children !== null) {
            $children[$rand] = $newButton;
        }
    }
    $buttonsData["buttons"] = $buttons;
    add("lecture_buttons", $buttonsData);
    sendmsg($chat_id, " تم اضافة المجلد: $text", [
        [["text" => " العودة للوحة", "callback_data" => "l_admin_panel"]],
    ]);
    $lecture["mode"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 12.7: Admin - Create New File Button - Step 1 (l_admin_add_file)
 * ------------------------------------------------------------------------
 * Initiates the file creation workflow. First, the admin must provide
 * a name for the file (this becomes the button label). After the name
 * is received, the bot will ask for the actual file upload.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_admin_add_file") {
    editmsg($chat_id, $message_id, "ارسل اسم الملف اولا:", [
        [["text" => " الغاء", "callback_data" => "l_admin_panel"]],
    ]);
    $lecture["mode"][$chat_id] = "l_wait_file_name";
    saveLectureAdmin($lecture);
    exit(0);
}

/**
 * ------------------------------------------------------------------------
 * HANDLER 12.8: Admin - Receive File Name (text handler)
 * ------------------------------------------------------------------------
 * Stores the file name in the admin's temporary session data and
 * transitions the mode to "l_wait_file_upload" to await the actual
 * file upload from the admin.
 * ------------------------------------------------------------------------
 */
if (isset($text) && $mode === "l_wait_file_name") {
    $lecture["temp"][$chat_id]["file_name"] = $text;
    $lecture["mode"][$chat_id] = "l_wait_file_upload";
    saveLectureAdmin($lecture);
    sendmsg($chat_id, " تم حفظ الاسم: $text\n\nالان ارسل الملف (PDF, فيديو, صوره, صوت):", [
        [["text" => " الغاء", "callback_data" => "l_admin_panel"]],
    ]);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 12.9: Admin - Receive File Upload (document/video/audio/photo handler)
 * ------------------------------------------------------------------------
 *
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║                   CRITICAL FILE STORAGE PIPELINE                    ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 *
 * This is the most important handler in the bot. It implements the
 * secure file storage mechanism:
 *
 * Step 1 - Verification:
 *   Checks that a storage channel has been configured by the owner.
 *   If not, the upload is rejected with an error message.
 *
 * Step 2 - Capture:
 *   Extracts the file_id from whatever the admin uploaded (document,
 *   video, audio, or photo). For photos, the highest resolution
 *   version is selected.
 *
 * Step 3 - Forward to Storage Channel:
 *   The file is immediately sent to the designated storage channel.
 *   This ensures the file is permanently hosted on Telegram's servers
 *   and will never expire or become inaccessible.
 *
 * Step 4 - Capture New file_id:
 *   When Telegram receives the file in the storage channel, it returns
 *   a NEW file_id specific to the bot. This file_id is what gets saved.
 *   This is critical because file_ids can differ between bots.
 *
 * Step 5 - Persist:
 *   The new file_id, along with the file name and MIME type, is saved
 *   as a file-type button in the hierarchical JSON structure at the
 *   admin's current navigation level.
 *
 * BENEFITS OF THIS APPROACH:
 *   - Files are never lost, even if the admin deletes the original
 *   - All files are centrally organized in one channel
 *   - Telegram handles all storage and bandwidth
 *   - File IDs can be shared across the bot's scope
 * ------------------------------------------------------------------------
 */
if (($document || $video || $audio || $photo) && ($mode === "l_wait_file_upload" || $mode === "l_wait_file_more")) {
    /**
     * Verify that a storage channel has been configured.
     * Without this, files cannot be securely persisted.
     */
    if (empty($storage_channel)) {
        sendmsg($chat_id, " لم يتم تعيين قناة التخزين بعد. يرجى من المالك تعيينها اولا.", [
            [["text" => " العودة", "callback_data" => "l_admin_panel"]],
        ]);
        $lecture["mode"][$chat_id] = null;
        saveLectureAdmin($lecture);
        exit(0);
    }

    /**
     * Extract the file_id based on the media type.
     * Each media type in Telegram has a slightly different structure
     * for accessing the file_id property.
     */
    $file_type = null;
    if ($document) {
        $file_id = $document->file_id;
        $mime = isset($document->mime_type) ? $document->mime_type : "document";
        $file_type = "document";
    } elseif ($video) {
        $file_id = $video->file_id;
        $mime = "video";
        $file_type = "video";
    } elseif ($audio) {
        $file_id = $audio->file_id;
        $mime = "audio";
        $file_type = "audio";
    } elseif ($photo && is_array($photo) && count($photo) > 0) {
        /**
         * Photos are sent as an array of progressively larger sizes.
         * We use the last (largest) element for the best quality.
         */
        $file_id = $photo[count($photo) - 1]->file_id;
        $mime = "photo";
        $file_type = "photo";
    } else {
        sendmsg($chat_id, " لم يتم التعرف على نوع الملف.", [
            [["text" => " العودة", "callback_data" => "l_admin_panel"]],
        ]);
        exit(0);
    }

    $name = $lecture["temp"][$chat_id]["file_name"];
    $caption = " $name";

    /**
     * Step 3 & 4: Forward the file to the storage channel and capture
     * the new file_id returned by Telegram.
     *
     * This two-step process (upload  receive new ID) ensures the file
     * is permanently stored on Telegram's infrastructure with an ID
     * that is valid for this bot's scope.
     * NOTE: must use matching send* method per type, otherwise
     * Telegram rejects photo file_ids sent as document.
     */
    $newFileId = storeLectureFile($storage_channel, $file_id, $file_type, $caption);

    if ($newFileId !== false && $newFileId !== null) {
        $buttons = $buttonsData["buttons"];
        $nav = $lecture["nav"][$chat_id];

        if ($mode === "l_wait_file_more" && isset($lecture["temp"][$chat_id]["button_id"])) {
            /**
             * Adding more files to an existing button.
             * Append this file to the files[] array.
             */
            $existingId = $lecture["temp"][$chat_id]["button_id"];
            $foundBtn = false;
            if ($nav === null || $nav === "root") {
                if (isset($buttons[$existingId])) {
                    $foundBtn = true;
                    $targetRef = &$buttons[$existingId];
                }
            } else {
                $parentChildren = &findFolderChildrenRef($buttons, $nav);
                if ($parentChildren !== null && isset($parentChildren[$existingId])) {
                    $foundBtn = true;
                    $targetRef = &$parentChildren[$existingId];
                }
            }
            if ($foundBtn) {
                $targetRef["files"][] = [
                    "file_id" => $newFileId,
                    "mime" => $mime,
                ];
                $fileCount = count($targetRef["files"]);
                $buttonsData["buttons"] = $buttons;
                add("lecture_buttons", $buttonsData);
                $lecture["mode"][$chat_id] = "l_wait_file_more";
                saveLectureAdmin($lecture);
                sendmsg($chat_id, " تم اضافة الملف رقم $fileCount بنجاح\n\nارسـل ملف آخر او اضغط انهاء:", [
                    [["text" => " انهاء", "callback_data" => "l_admin_finish_files"]],
                    [["text" => " الغاء", "callback_data" => "l_admin_panel"]],
                ]);
                exit(0);
            }
        }

        /**
         * First file for a new button: create the button with a files array.
         */
        $rand = substr(str_shuffle("abcdefghijklmopqrstvwxyzABCDEFGHIJKLMNOPQRSTUVXYZ1234567890"), 0, 8);
        $newButton = [
            "name" => $name,
            "type" => "file",
            "files" => [
                [
                    "file_id" => $newFileId,
                    "mime" => $mime,
                ],
            ],
        ];
        if ($nav === null || $nav === "root") {
            $buttons[$rand] = $newButton;
        } else {
            $children = &findFolderChildrenRef($buttons, $nav);
            if ($children !== null) {
                $children[$rand] = $newButton;
            }
        }
        $buttonsData["buttons"] = $buttons;
        add("lecture_buttons", $buttonsData);

        /**
         * Store the button ID in temp and set mode to l_wait_file_more
         * so the admin can continue uploading more files.
         */
        $lecture["temp"][$chat_id]["button_id"] = $rand;
        $lecture["mode"][$chat_id] = "l_wait_file_more";
        saveLectureAdmin($lecture);

        sendmsg($chat_id, " تم اضافة الملف: $name بنجاح ($mime)\n\nيمكنك اضافة المزيد من الملفات لنفس الزر:", [
            [["text" => " انهاء", "callback_data" => "l_admin_finish_files"]],
            [["text" => " العودة للوحة", "callback_data" => "l_admin_panel"]],
        ]);
    } else {
        /**
         * The file could not be forwarded to the storage channel.
         * This can happen if the bot is no longer an admin in the
         * storage channel or if the channel has been deleted.
         */
        sendmsg($chat_id, " فشل في ارسال الملف الى قناة التخزين", [
            [["text" => " العودة", "callback_data" => "l_admin_panel"]],
        ]);
        $lecture["mode"][$chat_id] = null;
        unset($lecture["temp"][$chat_id]);
        saveLectureAdmin($lecture);
    }
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 12.10: Admin - Finish Adding Files (l_admin_finish_files)
 * ------------------------------------------------------------------------
 * Called when the admin clicks "Finish" after uploading all files
 * to a button. Clears the admin's mode state.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_admin_finish_files") {
    $lecture["mode"][$chat_id] = null;
    unset($lecture["temp"][$chat_id]["button_id"]);
    saveLectureAdmin($lecture);
    editmsg($chat_id, $message_id, " تم الانتهاء من اضافة الملفات", [
        [["text" => " العودة للوحة", "callback_data" => "l_admin_panel"]],
    ]);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 12.11: Admin - Delete Button (l_admin_delete_<id>)
 * ------------------------------------------------------------------------
 * Removes a button (folder or file) from the hierarchy at the current
 * navigation level. The button is matched either at the root level or
 * within the children of the currently open folder.
 *
 *  Deleting a folder permanently removes ALL nested content within it.
 *
 * Callback Pattern: l_admin_delete_<8-character-button-id>
 * ------------------------------------------------------------------------
 */
if (isset($data) && preg_match("/^l_admin_delete_/", $data)) {
    $replace = str_replace("l_admin_delete_", "", $data);
    $buttons = $buttonsData["buttons"];
    $nav = $lecture["nav"][$chat_id];
    $deleted = false;
    if ($nav === null || $nav === "root") {
        if (isset($buttons[$replace])) {
            unset($buttons[$replace]);
            $deleted = true;
        }
    } else {
        $parentChildren = &findFolderChildrenRef($buttons, $nav);
        if ($parentChildren !== null && isset($parentChildren[$replace])) {
            unset($parentChildren[$replace]);
            $deleted = true;
        }
    }
    if ($deleted) {
        $buttonsData["buttons"] = $buttons;
        add("lecture_buttons", $buttonsData);
        editmsg($chat_id, $message_id, " تم الحذف بنجاح", [
            [["text" => " العودة", "callback_data" => "l_admin_panel"]],
        ]);
    }
    $lecture["mode"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 12.12: Admin - Edit Button Options (l_admin_edit_<id>)
 * ------------------------------------------------------------------------
 * Shows the edit options for a selected button. The admin can choose
 * to modify:
 *   - The button's display name
 *   - The file's caption/description (download caption)
 *   - Manage individual files within the button (add/delete/view)
 *
 * Callback Pattern: l_admin_edit_<8-character-button-id>
 * ------------------------------------------------------------------------
 */
if (isset($data) && preg_match("/^l_admin_edit_/", $data)) {
    $replace = str_replace("l_admin_edit_", "", $data);
    $lecture["temp"][$chat_id]["edit_key"] = $replace;
    saveLectureAdmin($lecture);
    editmsg($chat_id, $message_id, "اختر ما تريد تعديله:", [
        [
            ["text" => " تغيير الاسم", "callback_data" => "l_admin_edit_name"],
            ["text" => " تغيير الوصف", "callback_data" => "l_admin_edit_caption"],
        ],
        [
            ["text" => " ادارة الملفات", "callback_data" => "l_admin_manage_files"],
            ["text" => " حذف الزر", "callback_data" => "l_admin_delete_from_edit_" . $replace],
        ],
        [
            ["text" => " الغاء", "callback_data" => "l_admin_panel"],
        ],
    ]);
    $lecture["mode"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}

/**
 * ------------------------------------------------------------------------
 * HANDLER 12.13: Admin - Manage Files in Button (l_admin_manage_files)
 * ------------------------------------------------------------------------
 * Shows the list of files inside a file-type button. The admin can
 * view each file, delete individual files, or add more files.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_admin_manage_files") {
    $buttons = $buttonsData["buttons"];
    $nav = $lecture["nav"][$chat_id];
    $key = $lecture["temp"][$chat_id]["edit_key"];
    $targetBtn = null;
    if ($nav === null || $nav === "root") {
        if (isset($buttons[$key])) {
            $targetBtn = $buttons[$key];
        }
    } else {
        $parentChildren = &findFolderChildrenRef($buttons, $nav);
        if ($parentChildren !== null && isset($parentChildren[$key])) {
            $targetBtn = $parentChildren[$key];
        }
    }
    if ($targetBtn === null || $targetBtn["type"] !== "file") {
        editmsg($chat_id, $message_id, " هذا الزر ليس من نوع ملف", [
            [["text" => " رجوع", "callback_data" => "l_admin_panel"]],
        ]);
        exit(0);
    }
    $filesList = isset($targetBtn["files"]) ? $targetBtn["files"] : [];
    if (isset($targetBtn["file_id"])) {
        $filesList = [["file_id" => $targetBtn["file_id"], "mime" => isset($targetBtn["mime"]) ? $targetBtn["mime"] : "unknown"]];
    }
    $fileKeyboard = [];
    foreach ($filesList as $idx => $fileEntry) {
        $fileLabel = isset($fileEntry["caption"]) ? $fileEntry["caption"] : "ملف " . ($idx + 1);
        $fileIcon = "";
        $fileKeyboard[] = [
            ["text" => "$fileIcon $fileLabel", "callback_data" => "l_admin_delete_file_" . $idx],
        ];
    }
    $fileKeyboard[] = [
        ["text" => " اضافة ملفات جديدة", "callback_data" => "l_admin_add_more_files"],
    ];
    $fileKeyboard[] = [
        ["text" => " رجوع", "callback_data" => "l_admin_edit_" . $key],
    ];
    $total = count($filesList);
    editmsg($chat_id, $message_id, " ادارة ملفات: " . $targetBtn["name"] . "\nعدد الملفات: $total\n\nاضغط على ملف لحذفه:", $fileKeyboard);
    exit(0);
}

/**
 * ------------------------------------------------------------------------
 * HANDLER 12.14: Admin - Delete Individual File (l_admin_delete_file_<idx>)
 * ------------------------------------------------------------------------
 * Removes a single file from the button's files array by index.
 * If only one file remains, the entire button is deleted.
 *
 * Callback Pattern: l_admin_delete_file_<0-based-index>
 * ------------------------------------------------------------------------
 */
if (isset($data) && preg_match("/^l_admin_delete_file_/", $data)) {
    $fileIdx = (int)str_replace("l_admin_delete_file_", "", $data);
    $buttons = $buttonsData["buttons"];
    $nav = $lecture["nav"][$chat_id];
    $key = $lecture["temp"][$chat_id]["edit_key"];
    $modified = false;
    if ($nav === null || $nav === "root") {
        if (isset($buttons[$key]) && $buttons[$key]["type"] === "file") {
            if (isset($buttons[$key]["files"]) && isset($buttons[$key]["files"][$fileIdx])) {
                array_splice($buttons[$key]["files"], $fileIdx, 1);
                if (empty($buttons[$key]["files"])) {
                    unset($buttons[$key]);
                }
                $modified = true;
            } elseif (isset($buttons[$key]["file_id"])) {
                unset($buttons[$key]);
                $modified = true;
            }
        }
    } else {
        $parentChildren = &findFolderChildrenRef($buttons, $nav);
        if ($parentChildren !== null && isset($parentChildren[$key]) && $parentChildren[$key]["type"] === "file") {
            if (isset($parentChildren[$key]["files"]) && isset($parentChildren[$key]["files"][$fileIdx])) {
                array_splice($parentChildren[$key]["files"], $fileIdx, 1);
                if (empty($parentChildren[$key]["files"])) {
                    unset($parentChildren[$key]);
                }
                $modified = true;
            } elseif (isset($parentChildren[$key]["file_id"])) {
                unset($parentChildren[$key]);
                $modified = true;
            }
        }
    }
    if ($modified) {
        $buttonsData["buttons"] = $buttons;
        add("lecture_buttons", $buttonsData);
        editmsg($chat_id, $message_id, " تم حذف الملف", [
            [["text" => " العودة للوحة", "callback_data" => "l_admin_panel"]],
        ]);
    }
    $lecture["mode"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}

/**
 * ------------------------------------------------------------------------
 * HANDLER 12.15: Admin - Add More Files to Existing Button (l_admin_add_more_files)
 * ------------------------------------------------------------------------
 * Puts the admin in upload mode to add more files to an existing
 * file-type button. Subsequent file uploads will be appended to
 * this button's files array.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_admin_add_more_files") {
    $key = $lecture["temp"][$chat_id]["edit_key"];
    $lecture["temp"][$chat_id]["button_id"] = $key;
    $lecture["mode"][$chat_id] = "l_wait_file_more";
    saveLectureAdmin($lecture);
    editmsg($chat_id, $message_id, "ارسل الملفات التي تريد اضافتها (PDF, فيديو, صوره, صوت):", [
        [["text" => " انهاء", "callback_data" => "l_admin_finish_files"]],
        [["text" => " الغاء", "callback_data" => "l_admin_panel"]],
    ]);
    exit(0);
}

/**
 * ------------------------------------------------------------------------
 * HANDLER 12.16: Admin - Delete Button from Edit Menu (l_admin_delete_from_edit_<id>)
 * ------------------------------------------------------------------------
 * Quick delete button placed directly in the edit options menu.
 *
 * Callback Pattern: l_admin_delete_from_edit_<8-character-button-id>
 * ------------------------------------------------------------------------
 */
if (isset($data) && preg_match("/^l_admin_delete_from_edit_/", $data)) {
    $replace = str_replace("l_admin_delete_from_edit_", "", $data);
    $buttons = $buttonsData["buttons"];
    $nav = $lecture["nav"][$chat_id];
    $deleted = false;
    if ($nav === null || $nav === "root") {
        if (isset($buttons[$replace])) {
            unset($buttons[$replace]);
            $deleted = true;
        }
    } else {
        $parentChildren = &findFolderChildrenRef($buttons, $nav);
        if ($parentChildren !== null && isset($parentChildren[$replace])) {
            unset($parentChildren[$replace]);
            $deleted = true;
        }
    }
    if ($deleted) {
        $buttonsData["buttons"] = $buttons;
        add("lecture_buttons", $buttonsData);
        editmsg($chat_id, $message_id, " تم الحذف بنجاح", [
            [["text" => " العودة للوحة", "callback_data" => "l_admin_panel"]],
        ]);
    }
    $lecture["mode"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}

/**
 * ------------------------------------------------------------------------
 * HANDLER 12.17: Admin - Edit Name (l_admin_edit_name)
 * ------------------------------------------------------------------------
 * Prompts the admin to enter a new name for the selected button.
 * The button to edit is identified by the edit_key stored in temp data.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_admin_edit_name") {
    editmsg($chat_id, $message_id, "ارسل الاسم الجديد:", [
        [["text" => " الغاء", "callback_data" => "l_admin_panel"]],
    ]);
    $lecture["mode"][$chat_id] = "l_wait_edit_name";
    saveLectureAdmin($lecture);
    exit(0);
}

/**
 * ------------------------------------------------------------------------
 * HANDLER 12.18: Admin - Receive New Name (text handler)
 * ------------------------------------------------------------------------
 * Updates the button's name with the admin-provided text. The target
 * button is located using the stored edit_key at the current nav level.
 * ------------------------------------------------------------------------
 */
if (isset($text) && $mode === "l_wait_edit_name") {
    $buttons = $buttonsData["buttons"];
    $nav = $lecture["nav"][$chat_id];
    $key = $lecture["temp"][$chat_id]["edit_key"];
    $found = false;
    if ($nav === null || $nav === "root") {
        if (isset($buttons[$key])) {
            $buttons[$key]["name"] = $text;
            $found = true;
        }
    } else {
        $parentChildren = &findFolderChildrenRef($buttons, $nav);
        if ($parentChildren !== null && isset($parentChildren[$key])) {
            $parentChildren[$key]["name"] = $text;
            $found = true;
        }
    }
    if ($found) {
        $buttonsData["buttons"] = $buttons;
        add("lecture_buttons", $buttonsData);
        sendmsg($chat_id, " تم تغيير الاسم الى: $text", [
            [["text" => " العودة", "callback_data" => "l_admin_panel"]],
        ]);
    }
    $lecture["mode"][$chat_id] = null;
    unset($lecture["temp"][$chat_id]["edit_key"]);
    saveLectureAdmin($lecture);
    exit(0);
}

/**
 * ------------------------------------------------------------------------
 * HANDLER 12.19: Admin - Edit Caption (l_admin_edit_caption)
 * ------------------------------------------------------------------------
 * Prompts the admin to enter a new caption/description for the
 * selected file. This caption appears when students download the file.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_admin_edit_caption") {
    editmsg($chat_id, $message_id, "ارسل الوصف الجديد (او ارسل /skip لتخطي):", [
        [["text" => " الغاء", "callback_data" => "l_admin_panel"]],
    ]);
    $lecture["mode"][$chat_id] = "l_wait_edit_caption";
    saveLectureAdmin($lecture);
    exit(0);
}

/**
 * ------------------------------------------------------------------------
 * HANDLER 12.20: Admin - Receive New Caption (text handler)
 * ------------------------------------------------------------------------
 * Updates the button's caption with the admin-provided text.
 * ------------------------------------------------------------------------
 */
if (isset($text) && $mode === "l_wait_edit_caption") {
    $buttons = $buttonsData["buttons"];
    $nav = $lecture["nav"][$chat_id];
    $key = $lecture["temp"][$chat_id]["edit_key"];
    $found = false;
    $caption = $text;
    if ($nav === null || $nav === "root") {
        if (isset($buttons[$key])) {
            $buttons[$key]["caption"] = $caption;
            $found = true;
        }
    } else {
        $parentChildren = &findFolderChildrenRef($buttons, $nav);
        if ($parentChildren !== null && isset($parentChildren[$key])) {
            $parentChildren[$key]["caption"] = $caption;
            $found = true;
        }
    }
    if ($found) {
        $buttonsData["buttons"] = $buttons;
        add("lecture_buttons", $buttonsData);
        sendmsg($chat_id, " تم حفظ الوصف", [
            [["text" => " العودة", "callback_data" => "l_admin_panel"]],
        ]);
    }
    $lecture["mode"][$chat_id] = null;
    unset($lecture["temp"][$chat_id]["edit_key"]);
    saveLectureAdmin($lecture);
    exit(0);
}


/**
 * ========================================================================
 * SECTION 13: OWNER EXCLUSIVE HANDLERS
 * ========================================================================
 * These handlers provide the owner's configuration dashboard.
 * All handlers in this section enforce ownership verification
 * before executing any privileged operations.
 * ========================================================================
 */


/**
 * ------------------------------------------------------------------------
 * HANDLER 13.1: Owner Dashboard (l_owner_panel)
 * ------------------------------------------------------------------------
 * Displays the owner's configuration panel showing:
 *   - The current list of administrators
 *   - The configured storage channel (or "Not Set")
 *   - Action buttons for managing admins and storage
 *
 *  ACCESS: Owner only. Non-owners receive an error message.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_owner_panel") {
    if (!isOwner($chat_id)) {
        editmsg($chat_id, $message_id, " هذه القائمة للمالك فقط", [
            [["text" => " رجوع", "callback_data" => "l_admin_panel"]],
        ]);
        exit(0);
    }
    $adminList = !empty($admins) ? implode("\n", $admins) : "لا يوجد";
    $channelInfo = $storage_channel ? $storage_channel : "غير محدد";
    $msg = " لوحة المالك\n\n قائمة الادمنية:\n$adminList\n\n قناة التخزين: $channelInfo";
    editmsg($chat_id, $message_id, $msg, [
        [
            ["text" => " اضافة ادمن", "callback_data" => "l_owner_add_admin"],
            ["text" => " حذف ادمن", "callback_data" => "l_owner_remove_admin"],
        ],
        [
            ["text" => " تعيين قناة تخزين", "callback_data" => "l_owner_set_storage"],
        ],
        [
            ["text" => " رجوع", "callback_data" => "l_admin_panel"],
        ],
    ]);
    $lecture["mode"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 13.2: Owner - Add Admin (l_owner_add_admin)
 * ------------------------------------------------------------------------
 * Initiates the admin addition workflow. The owner is prompted to
 * enter the Telegram user ID of the person they want to grant
 * administrative privileges to.
 *
 *  ACCESS: Owner only.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_owner_add_admin") {
    if (!isOwner($chat_id)) {
        exit(0);
    }
    editmsg($chat_id, $message_id, "ارسل ايدي الشخص لاضافته كادمن:", [
        [["text" => " الغاء", "callback_data" => "l_owner_panel"]],
    ]);
    $lecture["mode"][$chat_id] = "l_owner_wait_admin_id";
    saveLectureAdmin($lecture);
    exit(0);
}

/**
 * ------------------------------------------------------------------------
 * HANDLER 13.3: Owner - Receive Admin ID (text handler)
 * ------------------------------------------------------------------------
 * Processes the ID submitted by the owner. If the ID is not already
 * in the admin list, it is added. Duplicate IDs are rejected with
 * an appropriate message.
 *
 *  ACCESS: Owner only.
 * ------------------------------------------------------------------------
 */
if (isset($text) && $mode === "l_owner_wait_admin_id") {
    if (!isOwner($chat_id)) {
        exit(0);
    }
    $id = trim($text);
    if (!in_array($id, $settings["admins"])) {
        $settings["admins"][] = $id;
        add("lecture_settings", $settings);
        sendmsg($chat_id, " تم اضافة $id كادمن بنجاح", [
            [["text" => " العودة", "callback_data" => "l_owner_panel"]],
        ]);
    } else {
        sendmsg($chat_id, " هذا الايدي مضاف مسبقا", [
            [["text" => " العودة", "callback_data" => "l_owner_panel"]],
        ]);
    }
    $lecture["mode"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 13.4: Owner - Remove Admin List (l_owner_remove_admin)
 * ------------------------------------------------------------------------
 * Displays a list of all current administrators, each as a button.
 * The owner can click any admin ID to remove them.
 *
 *  ACCESS: Owner only.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_owner_remove_admin") {
    if (!isOwner($chat_id)) {
        exit(0);
    }
    $adminList = [];
    foreach ($admins as $admin) {
        $adminList[] = [
            ["text" => $admin, "callback_data" => "l_owner_remove_" . $admin],
        ];
    }
    $adminList[] = [["text" => " الغاء", "callback_data" => "l_owner_panel"]];
    editmsg($chat_id, $message_id, "اختر الادمن لحذفه:", $adminList);
    $lecture["mode"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}

/**
 * ------------------------------------------------------------------------
 * HANDLER 13.5: Owner - Confirm Admin Removal (l_owner_remove_<id>)
 * ------------------------------------------------------------------------
 * Processes the removal of an administrator. The admin ID is removed
 * from the settings array using array_diff.
 *
 *  ACCESS: Owner only.
 *
 * Callback Pattern: l_owner_remove_<telegram-user-id>
 * ------------------------------------------------------------------------
 */
if (isset($data) && preg_match("/^l_owner_remove_/", $data)) {
    if (!isOwner($chat_id)) {
        exit(0);
    }
    $replace = str_replace("l_owner_remove_", "", $data);
    $settings["admins"] = array_values(array_diff($settings["admins"], [$replace]));
    add("lecture_settings", $settings);
    editmsg($chat_id, $message_id, " تم حذف $replace من الادمنية", [
        [["text" => " العودة", "callback_data" => "l_owner_panel"]],
    ]);
    $lecture["mode"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 13.6: Owner - Set Storage Channel (l_owner_set_storage)
 * ------------------------------------------------------------------------
 *
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║              STORAGE CHANNEL CONFIGURATION WORKFLOW                 ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 *
 * This handler initiates the storage channel setup. The owner must
 * forward a message FROM the target channel TO the bot. The bot
 * extracts the channel's chat ID from the forwarded message metadata
 * and saves it to the configuration.
 *
 * Why forwarding instead of typing the ID?
 *   - Channel IDs are negative numbers that are hard to type correctly
 *   - Forwarding proves the bot has access to the channel
 *   - It's a more user-friendly experience
 *
 * Prerequisites:
 *   1. The bot MUST be added as an administrator in the target channel
 *   2. The owner must forward ANY message from that channel to the bot
 *
 *  ACCESS: Owner only.
 * ------------------------------------------------------------------------
 */
if (isset($data) && $data === "l_owner_set_storage") {
    if (!isOwner($chat_id)) {
        exit(0);
    }
    editmsg($chat_id, $message_id, "قم بتوجيه رسالة من القناة التي تريد تعيينها كقناة تخزين:", [
        [["text" => " الغاء", "callback_data" => "l_owner_panel"]],
    ]);
    $lecture["mode"][$chat_id] = "l_owner_wait_storage_forward";
    saveLectureAdmin($lecture);
    exit(0);
}


/**
 * ------------------------------------------------------------------------
 * HANDLER 13.7: Owner - Receive Storage Channel Forward (forward handler)
 * ------------------------------------------------------------------------
 * Processes the forwarded message to extract the storage channel ID.
 *
 * When a user forwards a message from a channel, Telegram includes
 * the `forward_from_chat` object containing:
 *   - id: The channel's unique chat ID (always negative)
 *   - title: The channel's display name
 *   - type: "channel"
 *
 * This ID is saved to settings as the designated storage channel.
 * All future file uploads will be forwarded to this channel.
 *
 *  ACCESS: Owner only.
 * ------------------------------------------------------------------------
 */
if (isset($forward_from_chat) && $mode === "l_owner_wait_storage_forward") {
    if (!isOwner($chat_id)) {
        exit(0);
    }
    $channelId = $forward_from_chat->id;
    $channelTitle = isset($forward_from_chat->title) ? $forward_from_chat->title : "Unknown";
    $settings["storage_channel"] = $channelId;
    add("lecture_settings", $settings);
    sendmsg($chat_id, " تم تعيين قناة التخزين بنجاح\n\n اسم القناة: $channelTitle\n ايدي القناة: $channelId", [
        [["text" => " العودة", "callback_data" => "l_owner_panel"]],
    ]);
    $lecture["mode"][$chat_id] = null;
    saveLectureAdmin($lecture);
    exit(0);
}
