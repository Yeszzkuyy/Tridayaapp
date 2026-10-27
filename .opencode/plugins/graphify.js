// graphify OpenCode plugin (v2)
// Injects a knowledge graph reminder before shell tool calls when the graph exists.
//
// Ported from the OpenCode v1 API: v1 exported a plugin function returning a
// `"tool.execute.before"` hook. V2 default-exports `Plugin.define({ id, setup })`
// and registers the same behaviour with `ctx.tool.hook("execute.before", ...)`.
//
// IMPORTANT: keep the reminder string free of backticks and $(...) constructs.
// The hook prepends `echo "<reminder>" ; <cmd>` to the user's shell command;
// backticks inside the double-quoted echo trigger shell command substitution,
// which both corrupts tool output and silently executes the very graphify
// command we are only suggesting. Plain words render fine in opencode's TUI.
import { existsSync } from "fs";
import { join } from "path";
import { Plugin } from "@opencode/plugin";

// V2 names the shell tool "shell" in some builds and "bash" in others; accept both.
const SHELL_TOOLS = new Set(["bash", "shell"]);

const REMINDER =
  "[graphify] knowledge graph at graphify-out/. For focused questions, run graphify query with your question (scoped subgraph, usually much smaller than GRAPH_REPORT.md) instead of grepping raw files. Read GRAPH_REPORT.md only for broad architecture context.";

export default Plugin.define({
  id: "graphify",
  async setup(ctx) {
    let reminded = false;

    await ctx.tool.hook("execute.before", (event) => {
      if (reminded) return;
      if (!SHELL_TOOLS.has(event.tool)) return;

      const input = event.input;
      if (!input || typeof input.command !== "string") return;

      if (!existsSync(join(ctx.location.directory, "graphify-out", "graph.json"))) return;

      // ';' not '&&' — Windows PowerShell 5.1 rejects '&&' as a statement
      // separator, breaking the first shell command of the session (#1646).
      input.command = `echo "${REMINDER}" ; ` + input.command;
      reminded = true;
    });
  },
});
