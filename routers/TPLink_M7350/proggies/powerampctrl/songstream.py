#!/usr/bin/env python3
import socket
import time
import tkinter as tk
from tkinter import ttk, scrolledtext
import threading
import subprocess
import re

# Android telnet (direct connection)
ANDROID_HOST = "192.168.0.190"
ANDROID_PORT = 8023
WINDOWS_IP = "192.168.0.163"
AUDIO_STREAM_PORT = 12345

# ----- Color palette - Light Pink theme -----
COL_BG = "#fff5f7"
COL_PANEL = "#ffe4ed"
COL_PANEL_LIGHT = "#ffd9e4"
COL_ACCENT = "#ff69b4"
COL_ACCENT_DARK = "#ff1493"
COL_TEXT = "#4a2c3a"
COL_SUBTEXT = "#8b5e72"
COL_SUCCESS = "#2ecc71"
COL_WARNING = "#f39c12"
COL_ERROR = "#e74c3c"
COL_INFO = "#3498db"


class AudioStreamer:
    def __init__(self):
        self.process = None
        self.sock = None
        self.conn = None
        self.log_callback = None
        self.total_bytes = 0
        self.stop_flag = False
        self.stream_id = None
        self.lock = threading.Lock()

    def set_log_callback(self, callback):
        self.log_callback = callback

    def start_receiver(self, stream_id):
        """Start TCP server to receive audio stream"""
        self.stream_id = stream_id
        try:
            self.sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            self.sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            self.sock.settimeout(1.0)
            self.sock.bind(('0.0.0.0', AUDIO_STREAM_PORT))
            self.sock.listen(1)

            self.conn = None
            while not self.stop_flag and self.conn is None:
                try:
                    self.conn, addr = self.sock.accept()
                    print(f"[Audio {stream_id}] Connected from {addr}")
                except socket.timeout:
                    continue
                except Exception as e:
                    if not self.stop_flag:
                        print(f"[Audio {stream_id}] Accept error: {e}")
                    break

            if self.stop_flag or self.conn is None:
                print(f"[Audio {stream_id}] Stopped before connection")
                return False

            self.process = subprocess.Popen(
                ['ffplay', '-i', '-', '-nodisp', '-autoexit', '-loglevel', 'quiet'],
                stdin=subprocess.PIPE
            )

            bytes_received = 0
            last_log = time.time()

            while not self.stop_flag:
                try:
                    data = self.conn.recv(8192)
                    if not data:
                        print(f"[Audio {stream_id}] Connection closed by sender")
                        break
                    if self.process and self.process.stdin:
                        self.process.stdin.write(data)
                        bytes_received += len(data)
                        self.total_bytes = bytes_received

                        if time.time() - last_log >= 1:
                            mb = bytes_received / (1024 * 1024)
                            if self.log_callback:
                                self.log_callback(mb, stream_id)
                            last_log = time.time()
                except socket.timeout:
                    continue
                except BrokenPipeError:
                    print(f"[Audio {stream_id}] Broken pipe")
                    break
                except Exception as e:
                    if not self.stop_flag:
                        print(f"[Audio {stream_id}] Receive error: {e}")
                    break

            print(f"[Audio {stream_id}] Stream finished, received {bytes_received/(1024*1024):.2f} MB")
            return True

        except Exception as e:
            print(f"AudioStreamer error: {e}")
            return False
        finally:
            self.cleanup()

    def cleanup(self):
        """Clean up resources safely"""
        with self.lock:
            # Kill ffplay process
            if self.process:
                try:
                    if self.process.stdin:
                        self.process.stdin.close()
                except:
                    pass
                try:
                    self.process.terminate()
                    self.process.wait(timeout=2)
                except:
                    pass
                self.process = None
            
            # Close connection socket
            if self.conn:
                try:
                    self.conn.close()
                except:
                    pass
                self.conn = None
            
            # Close listening socket
            if self.sock:
                try:
                    self.sock.close()
                except:
                    pass
                self.sock = None

    def stop(self):
        """Stop the streamer"""
        print(f"[Audio {self.stream_id}] Stop requested")
        self.stop_flag = True
        # Don't call cleanup here - let the thread do it
        # Just close the connection to break out of recv
        if self.conn:
            try:
                self.conn.shutdown(socket.SHUT_RDWR)
            except:
                pass


class PowerampApp:
    def __init__(self, root):
        self.root = root
        self.root.title("Poweramp Streamer")
        self.root.geometry("900x750")
        self.root.configure(bg=COL_BG)

        self.current_song_path = None
        self.current_folder = None
        self.playlist = []
        self.playlist_index = -1
        self.is_playing = False
        self.auto_advance = tk.BooleanVar(value=True)
        self.streamer = None
        self.current_mb = 0

        self.stream_generation = 0
        self.active_stream_thread = None
        self.playing_lock = threading.Lock()

        self.setup_styles()
        self.setup_ui()
        self.root.after(500, self.get_song_and_folder)

    # ------------------------------------------------------------
    # Styling
    # ------------------------------------------------------------
    def setup_styles(self):
        style = ttk.Style()
        try:
            style.theme_use("clam")
        except tk.TclError:
            pass

        default_font = ("Segoe UI", 10)
        bold_font = ("Segoe UI", 10, "bold")

        style.configure(".", background=COL_BG, foreground=COL_TEXT, font=default_font)
        style.configure("TFrame", background=COL_BG)
        style.configure("TLabel", background=COL_BG, foreground=COL_TEXT, font=default_font)

        style.configure(
            "TLabelframe",
            background=COL_PANEL,
            foreground=COL_TEXT,
            borderwidth=1,
            relief="flat",
        )
        style.configure(
            "TLabelframe.Label",
            background=COL_PANEL,
            foreground=COL_ACCENT_DARK,
            font=bold_font,
        )

        style.configure(
            "Pill.TButton",
            background=COL_ACCENT,
            foreground="#ffffff",
            font=("Segoe UI", 10, "bold"),
            borderwidth=0,
            focusthickness=0,
            focuscolor=COL_ACCENT,
            padding=(14, 8),
            relief="flat",
        )
        style.map(
            "Pill.TButton",
            background=[("active", COL_ACCENT_DARK), ("pressed", COL_ACCENT_DARK)],
        )

        style.configure(
            "Accent.TButton",
            background=COL_ACCENT_DARK,
            foreground="#ffffff",
            font=("Segoe UI", 10, "bold"),
            borderwidth=0,
            focusthickness=0,
            focuscolor=COL_ACCENT_DARK,
            padding=(16, 8),
            relief="flat",
        )
        style.map(
            "Accent.TButton",
            background=[("active", "#db0f8b"), ("pressed", "#db0f8b")],
        )

        style.configure(
            "Danger.TButton",
            background="#d44c7a",
            foreground="#ffffff",
            font=("Segoe UI", 10, "bold"),
            borderwidth=0,
            focusthickness=0,
            focuscolor="#d44c7a",
            padding=(14, 8),
            relief="flat",
        )
        style.map(
            "Danger.TButton",
            background=[("active", "#c41e6a"), ("pressed", "#c41e6a")],
        )

        style.configure(
            "Nav.TButton",
            background=COL_ACCENT,
            foreground="#ffffff",
            font=("Segoe UI", 12, "bold"),
            borderwidth=0,
            focusthickness=0,
            focuscolor=COL_ACCENT,
            padding=(10, 6),
            relief="flat",
        )
        style.map(
            "Nav.TButton",
            background=[("active", COL_ACCENT_DARK), ("pressed", COL_ACCENT_DARK)],
        )

        style.configure(
            "TCheckbutton",
            background=COL_BG,
            foreground=COL_SUBTEXT,
            font=default_font,
        )
        style.map(
            "TCheckbutton",
            foreground=[("active", COL_TEXT)],
        )

        style.configure(
            "Accent.Horizontal.TProgressbar",
            troughcolor=COL_PANEL_LIGHT,
            background=COL_ACCENT,
            borderwidth=0,
            lightcolor=COL_ACCENT,
            darkcolor=COL_ACCENT,
            thickness=14,
        )

    # ------------------------------------------------------------
    # UI layout
    # ------------------------------------------------------------
    def setup_ui(self):
        main_frame = ttk.Frame(self.root, padding="12")
        main_frame.pack(fill=tk.BOTH, expand=True)

        self.status_label = ttk.Label(
            main_frame,
            text="Status: Auto-loading...",
            foreground=COL_WARNING,
            font=("Segoe UI", 11, "bold"),
        )
        self.status_label.pack(anchor=tk.W, pady=(0, 8))

        btn_frame = ttk.Frame(main_frame)
        btn_frame.pack(pady=5, fill=tk.X)

        ttk.Button(
            btn_frame, text="Get Current Song & Folder",
            style="Pill.TButton", command=self.get_song_and_folder
        ).pack(side=tk.LEFT, padx=4)

        ttk.Button(
            btn_frame, text="▶ Play",
            style="Accent.TButton", command=self.play_song
        ).pack(side=tk.LEFT, padx=4)

        ttk.Button(
            btn_frame, text="■ Stop",
            style="Danger.TButton", command=self.stop_song
        ).pack(side=tk.LEFT, padx=4)

        ttk.Button(
            btn_frame, text="⏮", width=3,
            style="Nav.TButton", command=self.prev_song
        ).pack(side=tk.LEFT, padx=(16, 2))

        ttk.Button(
            btn_frame, text="⏭", width=3,
            style="Nav.TButton", command=self.next_song
        ).pack(side=tk.LEFT, padx=2)

        ttk.Checkbutton(
            btn_frame, text="Auto-advance", variable=self.auto_advance
        ).pack(side=tk.LEFT, padx=16)

        progress_frame = ttk.LabelFrame(main_frame, text="Stream Progress", padding="10")
        progress_frame.pack(fill=tk.X, pady=8)

        self.progress_bar = ttk.Progressbar(
            progress_frame, mode='determinate', style="Accent.Horizontal.TProgressbar"
        )
        self.progress_bar.pack(fill=tk.X, pady=4)

        self.progress_label = ttk.Label(progress_frame, text="Waiting...", foreground=COL_SUBTEXT)
        self.progress_label.pack(pady=2)

        playlist_frame = ttk.LabelFrame(main_frame, text="Playlist (Double-click to play)", padding="10")
        playlist_frame.pack(fill=tk.BOTH, expand=True, pady=10)

        list_frame = ttk.Frame(playlist_frame)
        list_frame.pack(fill=tk.BOTH, expand=True)

        scrollbar = ttk.Scrollbar(list_frame)
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)

        self.playlist_box = tk.Listbox(
            list_frame, yscrollcommand=scrollbar.set, font=("Consolas", 9),
            bg=COL_PANEL_LIGHT, fg=COL_TEXT, selectbackground=COL_ACCENT,
            selectforeground="#ffffff", borderwidth=0, highlightthickness=0,
            activestyle="none",
        )
        self.playlist_box.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scrollbar.config(command=self.playlist_box.yview)

        self.playlist_box.bind('<Double-Button-1>', self.on_playlist_double_click)

        song_frame = ttk.LabelFrame(main_frame, text="Current Song", padding="10")
        song_frame.pack(fill=tk.X, pady=5)

        self.song_text = tk.Text(
            song_frame, height=2, wrap=tk.WORD,
            bg=COL_PANEL_LIGHT, fg=COL_TEXT, insertbackground=COL_TEXT,
            borderwidth=0, highlightthickness=0, font=("Consolas", 10),
        )
        self.song_text.pack(fill=tk.X)

        console_frame = ttk.LabelFrame(main_frame, text="Console", padding="10")
        console_frame.pack(fill=tk.BOTH, expand=True)

        self.console = scrolledtext.ScrolledText(
            console_frame, height=8, font=("Consolas", 9),
            bg=COL_PANEL_LIGHT, fg=COL_SUBTEXT, insertbackground=COL_TEXT,
            borderwidth=0, highlightthickness=0,
        )
        self.console.pack(fill=tk.BOTH, expand=True)

    def log(self, msg):
        self.console.insert(tk.END, f"[{time.strftime('%H:%M:%S')}] {msg}\n")
        self.console.see(tk.END)

    def update_progress(self, mb, stream_id):
        if stream_id != self.stream_generation:
            return
        self.current_mb = mb
        self.progress_label.config(text=f"Receiving: {mb:.2f} MB")
        percent = min(int((mb / 5) * 100), 100)
        self.progress_bar['value'] = percent

    def clean_filename(self, filename):
        """Remove all control characters, newlines, and trim whitespace from filename"""
        if not filename:
            return ""
        filename = re.sub(r'\x1b\[[0-?]*[ -/]*[@-~]', '', filename)
        filename = re.sub(r'[\x00-\x1f\x7f-\x9f]', '', filename)
        filename = filename.strip()
        return filename

    def send_telnet_command(self, cmd):
        """Send command directly to Android via telnet and get response"""
        sock = None
        try:
            sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            sock.settimeout(10)
            sock.connect((ANDROID_HOST, ANDROID_PORT))
            
            # Wait for initial prompt and clear it
            time.sleep(0.5)
            try:
                sock.recv(4096)
            except:
                pass
            
            # Send command
            sock.send(f"{cmd}\n".encode())
            time.sleep(1)
            
            # Get response
            response = b''
            sock.settimeout(2)
            try:
                while True:
                    data = sock.recv(4096)
                    if not data:
                        break
                    response += data
                    if b'~ $' in data or b'#' in data:
                        break
            except socket.timeout:
                pass
            
            return response.decode('utf-8', errors='ignore')
            
        except Exception as e:
            self.log(f"Telnet error: {e}")
            return ""
        finally:
            if sock:
                try:
                    sock.close()
                except:
                    pass

    def get_song_and_folder(self):
        self.status_label.config(text="Status: Getting song and folder...", foreground=COL_WARNING)
        threading.Thread(target=self._get_song_and_folder, daemon=True).start()

    def _get_song_and_folder(self):
        try:
            self.log("Getting current song path...")
            response = self.send_telnet_command(
                'su -c "ls -la /proc/20392/fd/ 2>/dev/null | grep /mobile/ | sed \'s/.*-> //\' | tail -1"'
            )

            response = self.clean_filename(response)
            
            start = response.find('/storage/')
            if start != -1:
                end = response.find('.mp3', start)
                if end != -1:
                    self.current_song_path = response[start:end + 4]
                    self.current_folder = '/'.join(self.current_song_path.split('/')[:-1]) + '/'

                    filename = self.current_song_path.split('/')[-1]
                    self.log(f"Found: {filename}")
                    self.log(f"Folder: {self.current_folder}")

                    self.log("Listing folder contents...")
                    ls_cmd = f'ls -1 "{self.current_folder}" | grep -E "\\.(mp3|flac|wav|m4a|ogg|opus)"'
                    ls_response = self.send_telnet_command(ls_cmd)

                    self.playlist = []
                    for line in ls_response.split('\n'):
                        line = self.clean_filename(line)
                        if line and re.search(r'\.(mp3|flac|wav|m4a|ogg|opus)$', line, re.IGNORECASE):
                            self.playlist.append(line)

                    self.playlist = sorted(self.playlist)

                    clean_filename = self.clean_filename(filename)
                    for i, song in enumerate(self.playlist):
                        if song == clean_filename:
                            self.playlist_index = i
                            break

                    self.playlist_box.delete(0, tk.END)
                    for song in self.playlist:
                        self.playlist_box.insert(tk.END, song)

                    if self.playlist_index >= 0:
                        self.playlist_box.selection_set(self.playlist_index)
                        self.playlist_box.see(self.playlist_index)

                    self.log(f"Found {len(self.playlist)} songs in folder")
                    self.song_text.delete(1.0, tk.END)
                    self.song_text.insert(1.0, self.current_song_path)
                    self.status_label.config(text="Status: Ready", foreground=COL_SUCCESS)
                    return

            self.log("No song found")
            self.status_label.config(text="Status: No song", foreground=COL_ERROR)

        except Exception as e:
            self.log(f"ERROR: {e}")
            self.status_label.config(text="Status: Error", foreground=COL_ERROR)

    def play_song(self):
        with self.playing_lock:
            if not self.current_song_path:
                self.log("No song loaded. Click 'Get Current Song & Folder' first.")
                return

            self.progress_bar['value'] = 0
            self.progress_label.config(text="Starting...")

            self.stream_generation += 1
            my_gen = self.stream_generation
            self.current_mb = 0

            if self.streamer:
                self.log("Stopping previous stream...")
                old_streamer = self.streamer
                self.streamer = None
                old_streamer.stop()
                # Give it time to fully stop
                time.sleep(1.5)

            self.log(f"Starting audio receiver (stream {my_gen})...")
            self.streamer = AudioStreamer()
            self.streamer.set_log_callback(lambda mb, sid: self.root.after(0, self.update_progress, mb, sid))

            def run_receiver():
                try:
                    self.streamer.start_receiver(my_gen)
                except Exception as e:
                    self.log(f"Receiver error: {e}")
                finally:
                    self.root.after(100, self.on_song_ended, my_gen)

            self.active_stream_thread = threading.Thread(target=run_receiver, daemon=True)
            self.active_stream_thread.start()

            # Wait for receiver to be ready
            time.sleep(2)

            clean_filename = self.clean_filename(self.current_song_path.split('/')[-1])
            self.log(f"Streaming: {clean_filename} (stream {my_gen})")
            
            # Escape single quotes in filename
            escaped_filename = clean_filename.replace("'", "'\\''")
            cmd = f"cat '{self.current_folder}{escaped_filename}' | nc {WINDOWS_IP} {AUDIO_STREAM_PORT}"

            def send_stream():
                try:
                    self.send_telnet_command(cmd)
                except Exception as e:
                    self.log(f"Stream send error: {e}")

            threading.Thread(target=send_stream, daemon=True).start()

            self.is_playing = True
            self.status_label.config(text="Status: Playing", foreground=COL_SUCCESS)

    def on_song_ended(self, gen):
        if gen != self.stream_generation:
            self.log(f"Ignoring end of stale stream {gen} (current is {self.stream_generation})")
            return

        with self.playing_lock:
            mb = self.current_mb
            if mb > 0:
                self.log(f"Song ended - Total: {mb:.2f} MB")
            else:
                self.log("Song ended")
            
            self.progress_bar['value'] = 0
            self.progress_label.config(text="Complete")
            self.current_mb = 0
            self.is_playing = False
            self.streamer = None

            if gen == self.stream_generation and self.auto_advance.get():
                self.log("Auto-advancing...")
                self.root.after(500, self._safe_next_song)

    def _safe_next_song(self):
        with self.playing_lock:
            if self.auto_advance.get() and not self.is_playing:
                self.next_song()

    def play_song_at_index(self, index):
        with self.playing_lock:
            if index < 0 or index >= len(self.playlist):
                self.log("End of playlist reached")
                self.stop_song()
                return False

            if self.streamer:
                old_streamer = self.streamer
                self.streamer = None
                old_streamer.stop()
                time.sleep(1)

            self.playlist_index = index
            clean_song = self.clean_filename(self.playlist[index])
            self.current_song_path = self.current_folder + clean_song
            self.song_text.delete(1.0, tk.END)
            self.song_text.insert(1.0, self.current_song_path)

            self.playlist_box.selection_clear(0, tk.END)
            self.playlist_box.selection_set(index)
            self.playlist_box.see(index)

        self.play_song()
        return True

    def next_song(self):
        if not self.playlist:
            return
        next_index = self.playlist_index + 1
        if next_index < len(self.playlist):
            self.play_song_at_index(next_index)
        else:
            self.log("End of playlist - stopping")
            self.stop_song()

    def prev_song(self):
        if not self.playlist:
            return
        prev_index = self.playlist_index - 1
        if prev_index >= 0:
            self.play_song_at_index(prev_index)
        else:
            self.log("Beginning of playlist")

    def stop_song(self):
        with self.playing_lock:
            self.stream_generation += 1
            
            if self.streamer:
                self.streamer.stop()
                self.streamer = None
            self.is_playing = False
            self.current_mb = 0
            
        self.status_label.config(text="Status: Stopped", foreground=COL_INFO)
        self.log("Stopped")
        self.progress_bar['value'] = 0
        self.progress_label.config(text="Waiting...")

    def on_playlist_double_click(self, event):
        selection = self.playlist_box.curselection()
        if selection:
            self.play_song_at_index(selection[0])


def main():
    root = tk.Tk()
    app = PowerampApp(root)
    root.mainloop()


if __name__ == "__main__":
    main()