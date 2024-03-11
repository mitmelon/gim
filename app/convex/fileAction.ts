import { mutation, action, internalMutation, } from "./_generated/server";
import { v } from "convex/values";
import { internal } from "./_generated/api";
import * as loginFunctions from './LoginFunctions';
import { Id } from "./_generated/dataModel";

export const storeFile = action({
  args: { sessionId: v.string(), fileUrl: v.string()},
  handler: async (ctx, args) => {

    const result = await ctx.runMutation(internal.fileAction.loggedIn, {
      sessionId: args.sessionId,
    });

    if(result !== null){
      // Download the image
      const response = await fetch(args.fileUrl);
      const image = await response.blob();
      
      // Store the image in Convex
      const storageId: Id<"_storage"> = await ctx.storage.store(image);
      // Write `storageId` to a document
      await ctx.runMutation(internal.fileAction.storeResult, {
        storageId,
        authToken: result,
      });
      return storageId;
    }
  },
});

export const storeCard = action({
  args: { sessionId: v.string(), fileUrl: v.string()},
  handler: async (ctx, args) => {

    const result = await ctx.runMutation(internal.fileAction.loggedIn, {
      sessionId: args.sessionId,
    });

    if(result !== null){
      // Download the image
      const response = await fetch(args.fileUrl);
      const image = await response.blob();
      
      // Store the image in Convex
      const storageId: Id<"_storage"> = await ctx.storage.store(image);
      // Write `storageId` to a document
      await ctx.runMutation(internal.fileAction.cardStorer, {
        storageId,
        authToken: result,
      });
      return storageId;
    }
  },
});

export const storeSignature = action({
  args: { sessionId: v.string(), fileUrl: v.string()},
  handler: async (ctx, args) => {

    const result = await ctx.runMutation(internal.fileAction.loggedIn, {
      sessionId: args.sessionId,
    });

    if(result !== null){
      // Download the image
      const response = await fetch(args.fileUrl);
      const image = await response.blob();
      
      // Store the image in Convex
      const storageId: Id<"_storage"> = await ctx.storage.store(image);
      // Write `storageId` to a document
      await ctx.runMutation(internal.fileAction.sigStorer, {
        storageId,
        authToken: result,
      });
      return storageId;
    }
  },
});

export const loggedIn = internalMutation({
  args: { sessionId: v.string() },
  handler: async (ctx, args) => {
    if (!args.sessionId) return false;

    const response = await loginFunctions.isLoggedIn(ctx, args.sessionId);
    if (response !== false && response !== null) {
      return response;
    }
    return null;
  },
});

export const storeResult = internalMutation({
  args: {
    storageId: v.id("_storage"),
    authToken: v.string(),
  },
  handler: async (ctx, args) => {

    const tasks = await ctx.db
    .query('todo_task')
    .filter((q) => q.eq(q.field("authToken"), args.authToken))
    .unique();

    const timestamp = parseInt(Math.round(+new Date() / 1000).toString());
    await ctx.db.insert("user_image", { authToken: args.authToken, storageId: args.storageId, dateUpdated: timestamp});

    if(tasks !== null){
      await ctx.db.patch(tasks._id, { facial: 'edited', dateUpdated: timestamp});
    } else {
      await ctx.db.insert("todo_task", { authToken: args.authToken, facial: 'filled', dateUpdated: timestamp});
    }
  },
});

export const cardStorer = internalMutation({
  args: {
    storageId: v.id("_storage"),
    authToken: v.string(),
  },
  handler: async (ctx, args) => {

    const tasks = await ctx.db
    .query('todo_task')
    .filter((q) => q.eq(q.field("authToken"), args.authToken))
    .unique();
    const timestamp = parseInt(Math.round(+new Date() / 1000).toString());

    const image = await ctx.db
        .query('identity_file')
        .filter((q) => q.eq(q.field("authToken"), args.authToken))
        .unique();
      if (image !== null) {
        await ctx.db.patch(image._id, { storageId: args.storageId, dateUpdated: timestamp});
      } else {
        await ctx.db.insert("identity_file", { authToken: args.authToken, storageId: args.storageId, dateUpdated: timestamp});
      }

    if(tasks !== null){
      await ctx.db.patch(tasks._id, { idcard: 'edited', dateUpdated: timestamp});
    } else {
      await ctx.db.insert("todo_task", { authToken: args.authToken, idcard: 'filled', dateUpdated: timestamp});
    }
  },
});

export const sigStorer = internalMutation({
  args: {
    storageId: v.id("_storage"),
    authToken: v.string(),
  },
  handler: async (ctx, args) => {

    const tasks = await ctx.db
    .query('todo_task')
    .filter((q) => q.eq(q.field("authToken"), args.authToken))
    .unique();

    const timestamp = parseInt(Math.round(+new Date() / 1000).toString());

    const image = await ctx.db
        .query('signature')
        .filter((q) => q.eq(q.field("authToken"), args.authToken))
        .unique();
      if (image !== null) {
        await ctx.db.patch(image._id, { storageId: args.storageId, dateUpdated: timestamp});
      } else {
        await ctx.db.insert("signature", { authToken: args.authToken, storageId: args.storageId, dateUpdated: timestamp});
      }

    if(tasks !== null){
      await ctx.db.patch(tasks._id, { signature: 'edited', dateUpdated: timestamp});
    } else {
      await ctx.db.insert("todo_task", { authToken: args.authToken, signature: 'filled', dateUpdated: timestamp});
    }
  },
});